<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Alur belanja di toko mitra: scan kartu -> (sesi) -> nominal + PIN -> transaksi. */
class MitraTransaksiService
{
    private const TTL_SESI_MENIT = 10;

    public function __construct(
        private SaldoService $saldo,
        private PinService $pin,
        private PengaturanService $pengaturan,
        private AuditService $audit,
    ) {
    }

    private function kunciSesi(string $id): string
    {
        return 'sesi_mitra:' . $id;
    }

    private function tokoDariUser(User $user): object
    {
        $toko = DB::table('toko_mitra')->where('user_id', $user->id)->first();
        if (!$toko) {
            throw new ApiException('TIDAK_BERWENANG', 'Akun ini bukan toko mitra.', 403);
        }

        return $toko;
    }

    /** Langkah 1: kasir memindai QR kartu. */
    public function mulai(User $mitraUser, string $qrToken): array
    {
        $toko = $this->tokoDariUser($mitraUser);

        $row = DB::table('kartu')
            ->join('nasabah', 'nasabah.id', '=', 'kartu.nasabah_id')
            ->join('users', 'users.id', '=', 'nasabah.user_id')
            ->where('kartu.qr_token', $qrToken)
            ->select('kartu.id as kartu_id', 'kartu.status as kartu_status', 'nasabah.id as nasabah_id',
                'nasabah.kode_nasabah', 'nasabah.saldo', 'users.id as user_id', 'users.nama',
                'users.status as user_status', 'users.terkunci_sampai')
            ->first();

        if (!$row) {
            throw new ApiException('KARTU_TIDAK_DITEMUKAN', 'Kartu tidak dikenal.', 404);
        }
        if ($row->kartu_status !== 'aktif') {
            throw new ApiException('KARTU_TIDAK_AKTIF', 'Kartu diblokir atau dilaporkan hilang.');
        }
        if ($row->user_status !== 'aktif') {
            throw new ApiException('AKUN_NONAKTIF', 'Akun nasabah tidak aktif.');
        }
        if ($row->terkunci_sampai && Carbon::parse($row->terkunci_sampai)->isFuture()) {
            throw new ApiException('AKUN_TERKUNCI', 'Akun nasabah terkunci sementara.', 423);
        }

        $sesiId = (string) Str::uuid();
        Cache::put($this->kunciSesi($sesiId), [
            'mitra_user_id' => $mitraUser->id,
            'toko_mitra_id' => $toko->id,
            'nasabah_id' => $row->nasabah_id,
            'nasabah_user_id' => $row->user_id,
            'kartu_id' => $row->kartu_id,
        ], now()->addMinutes(self::TTL_SESI_MENIT));

        return [
            'sesi_id' => $sesiId,
            'berlaku_sampai' => now()->addMinutes(self::TTL_SESI_MENIT)->toIso8601String(),
            'nasabah' => ['nama' => $row->nama, 'kode_nasabah' => $row->kode_nasabah, 'saldo' => $row->saldo],
        ];
    }

    /** Langkah 2: nominal + PIN nasabah (diketik di HP kasir). */
    public function konfirmasi(User $mitraUser, string $sesiId, string $nominal, string $pin): array
    {
        $kunci = $this->kunciSesi($sesiId);

        // Kunci sesi agar dua permintaan bersamaan tidak memproses sesi yang sama dua kali.
        return Cache::lock('kunci_' . $kunci, 15)->block(5, function () use ($mitraUser, $kunci, $nominal, $pin) {
            $sesi = Cache::get($kunci);
            if (!$sesi || $sesi['mitra_user_id'] !== $mitraUser->id) {
                throw new ApiException('SESI_KADALUARSA', 'Sesi transaksi tidak berlaku. Scan ulang kartu.', 410);
            }
            if (bccomp($nominal, '0', 2) <= 0) {
                throw new ApiException('NOMINAL_TIDAK_VALID', 'Nominal harus lebih dari 0.');
            }

            // Pastikan kartu dan akun masih aktif saat konfirmasi.
            $kartu = DB::table('kartu')->where('id', $sesi['kartu_id'])->first();
            $nasabahUser = User::findOrFail($sesi['nasabah_user_id']);
            if (!$kartu || $kartu->status !== 'aktif') {
                throw new ApiException('KARTU_TIDAK_AKTIF', 'Kartu diblokir atau dilaporkan hilang.');
            }
            if ($nasabahUser->status !== 'aktif') {
                throw new ApiException('AKUN_NONAKTIF', 'Akun nasabah tidak aktif.');
            }

            $this->pin->verifikasi($nasabahUser, $pin);

            $toko = DB::table('toko_mitra')->where('id', $sesi['toko_mitra_id'])->first();

            $hasil = DB::transaction(function () use ($sesi, $nominal, $mitraUser, $toko) {
                $trxId = DB::table('transaksi_mitra')->insertGetId([
                    'nasabah_id' => $sesi['nasabah_id'],
                    'kartu_id' => $sesi['kartu_id'],
                    'toko_mitra_id' => $sesi['toko_mitra_id'],
                    'nominal' => $nominal,
                ]);

                // Urutan kunci: nasabah dulu (di SaldoService), baru toko.
                $mutasi = $this->saldo->catat(
                    $sesi['nasabah_id'], 'belanja', 'debit', $nominal, 'transaksi_mitra', $trxId,
                    $mitraUser->id, 'Belanja di ' . $toko->nama_toko
                );

                DB::table('toko_mitra')->where('id', $toko->id)->lockForUpdate()->first();
                DB::table('toko_mitra')->where('id', $toko->id)->increment('saldo_tagihan', $nominal);

                $saldoTokoSesudah = DB::table('toko_mitra')->where('id', $toko->id)->value('saldo_tagihan');

                return [
                    'transaksi_id' => $trxId,
                    'nominal' => $nominal,
                    'saldo_nasabah_sesudah' => $mutasi->saldo_sesudah,
                    'saldo_toko_sesudah' => $saldoTokoSesudah,
                ];
            });

            Cache::forget($kunci);

            return $hasil;
        });
    }

    /** Pembatalan oleh mitra pemilik transaksi atau admin. */
    public function batal(User $user, int $transaksiId, string $alasan): array
    {
        return DB::transaction(function () use ($user, $transaksiId, $alasan) {
            $trx = DB::table('transaksi_mitra')->where('id', $transaksiId)->lockForUpdate()->first();
            if (!$trx) {
                throw new ApiException('TRANSAKSI_TIDAK_DITEMUKAN', 'Transaksi tidak ditemukan.', 404);
            }

            if ($user->role === 'mitra') {
                $toko = $this->tokoDariUser($user);
                if ($trx->toko_mitra_id !== $toko->id) {
                    throw new ApiException('TIDAK_BERWENANG', 'Transaksi ini bukan milik toko Anda.', 403);
                }
            } elseif (!in_array($user->role, ['admin', 'admin_utama'], true)) {
                throw new ApiException('TIDAK_BERWENANG', 'Anda tidak berwenang untuk aksi ini.', 403);
            }

            if ($trx->status !== 'berhasil') {
                throw new ApiException('SUDAH_DIBATALKAN', 'Transaksi sudah dibatalkan.');
            }

            $batasJam = $this->pengaturan->int('batas_batal_jam');
            if (Carbon::parse($trx->created_at)->addHours($batasJam)->isPast()) {
                throw new ApiException('LEWAT_BATAS_WAKTU', "Transaksi hanya bisa dibatalkan dalam {$batasJam} jam.");
            }

            if ($trx->pencairan_id !== null) {
                if ($user->role !== 'admin_utama') {
                    throw new ApiException('SUDAH_DICAIRKAN', 'Transaksi sudah masuk pencairan. Hubungi admin utama.');
                }
                $p = DB::table('pencairan_mitra')->where('id', $trx->pencairan_id)->lockForUpdate()->first();
                if ($p->status === 'dibayar') {
                    throw new ApiException('SUDAH_DICAIRKAN', 'Pencairan sudah dibayar. Selesaikan manual lewat admin utama.');
                }
                $sisa = bcsub($p->nominal, $trx->nominal, 2);
                if (bccomp($sisa, '0', 2) <= 0) {
                    DB::table('pencairan_mitra')->where('id', $p->id)->update(['status' => 'ditolak', 'updated_at' => now()]);
                } else {
                    DB::table('pencairan_mitra')->where('id', $p->id)->update(['nominal' => $sisa, 'updated_at' => now()]);
                }
            }

            $mutasi = $this->saldo->catat(
                $trx->nasabah_id, 'refund', 'kredit', $trx->nominal, 'transaksi_mitra', $trx->id, $user->id,
                'Pembatalan transaksi #' . $trx->id
            );

            $toko = DB::table('toko_mitra')->where('id', $trx->toko_mitra_id)->lockForUpdate()->first();
            if (bccomp($toko->saldo_tagihan, $trx->nominal, 2) < 0) {
                throw new ApiException('SALDO_TAGIHAN_KURANG', 'Saldo tagihan toko tidak mencukupi untuk pembatalan.');
            }
            DB::table('toko_mitra')->where('id', $toko->id)->decrement('saldo_tagihan', $trx->nominal);

            DB::table('transaksi_mitra')->where('id', $trx->id)->update([
                'status' => 'dibatalkan', 'dibatalkan_oleh' => $user->id, 'alasan_batal' => $alasan, 'waktu_batal' => now(),
            ]);
            $this->audit->catat($user->id, 'transaksi_mitra.batal', 'transaksi_mitra', ['status' => 'berhasil'], ['transaksi_id' => $trx->id, 'alasan' => $alasan]);

            return ['transaksi_id' => $trx->id, 'saldo_nasabah_sesudah' => $mutasi->saldo_sesudah];
        });
    }
}
