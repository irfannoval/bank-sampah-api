<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Penarikan saldo ke e-wallet/bank.
 * Saldo dikurangi sebesar nominal; biaya admin (ditanggung nasabah) dipotong dari nominal itu.
 */
class PenarikanService
{
    public function __construct(
        private SaldoService $saldo,
        private PinService $pin,
        private PengaturanService $pengaturan,
        private GatewayInterface $gateway,
        private AuditService $audit,
    ) {
    }

    public function biaya(string $nominal): array
    {
        $fee = bcadd($this->pengaturan->get('penarikan_biaya_admin'), '0', 2);

        return ['nominal' => $nominal, 'biaya_admin' => $fee, 'nominal_diterima' => bcsub($nominal, $fee, 2)];
    }

    public function validasiRekening(string $tipe, string $nomor): array
    {
        try {
            return $this->gateway->validasiRekening($tipe, $nomor);
        } catch (GatewayException $e) {
            throw new ApiException('REKENING_TIDAK_VALID', 'Rekening atau e-wallet tidak valid.', 422);
        }
    }

    /** $nasabahUser = pemilik PIN. $diprosesOleh terisi jika dibantu admin. */
    public function ajukan(User $nasabahUser, int $nasabahId, string $nominal, string $tipe, string $nomor, string $pin, ?int $diprosesOleh = null): array
    {
        $this->pin->verifikasi($nasabahUser, $pin);
        $info = $this->validasiRekening($tipe, $nomor);

        $penarikan = DB::transaction(function () use ($nasabahId, $nominal, $tipe, $nomor, $info, $diprosesOleh) {
            // Kunci baris nasabah DULU supaya hitungan batas harian tidak bisa disalip permintaan lain.
            $nasabah = DB::table('nasabah')->where('id', $nasabahId)->lockForUpdate()->first();
            if (!$nasabah) {
                throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
            }

            $this->periksaBatas($nasabah, $nominal);
            $b = $this->biaya($nominal);
            if (bccomp($b['nominal_diterima'], '0', 2) <= 0) {
                throw new ApiException('BATAS_PENARIKAN', 'Nominal terlalu kecil setelah dipotong biaya admin.');
            }

            $id = DB::table('penarikan')->insertGetId([
                'nasabah_id' => $nasabahId,
                'nominal' => $nominal,
                'biaya_admin' => $b['biaya_admin'],
                'nominal_diterima' => $b['nominal_diterima'],
                'tujuan_tipe' => $tipe,
                'tujuan_nomor' => $nomor,
                'nama_pemilik' => $info['nama'],
                'diproses_oleh' => $diprosesOleh,
            ]);

            $this->saldo->catat($nasabahId, 'penarikan', 'debit', $nominal, 'penarikan', $id, $diprosesOleh ?? $nasabah->user_id, 'Penarikan ke ' . $tipe);

            return DB::table('penarikan')->where('id', $id)->first();
        });

        // Kirim ke gateway SETELAH commit. Jika gagal dihubungi, tetap pending dan dicek ulang berkala.
        try {
            $hasil = $this->gateway->kirim($penarikan);
            DB::table('penarikan')->where('id', $penarikan->id)->update(['ref_gateway' => $hasil['ref'], 'updated_at' => now()]);
            if ($hasil['status'] !== 'pending') {
                $this->selesaikan($hasil['ref'], $hasil['status']);
            }
        } catch (GatewayException $e) {
            Log::warning('Gateway belum bisa dihubungi untuk penarikan #' . $penarikan->id . ': ' . $e->getMessage());
        }

        return (array) DB::table('penarikan')->where('id', $penarikan->id)->first();
    }

    private function periksaBatas(object $nasabah, string $nominal): void
    {
        $min = $this->pengaturan->get('penarikan_min');
        $maks = $this->pengaturan->get('penarikan_maks');

        if (bccomp($nominal, $min, 2) < 0) {
            throw new ApiException('BATAS_PENARIKAN', 'Minimal penarikan Rp' . number_format((float) $min, 0, ',', '.') . '.', 422, ['batas' => 'minimal']);
        }
        if (bccomp($nominal, $maks, 2) > 0) {
            throw new ApiException('BATAS_PENARIKAN', 'Maksimal penarikan Rp' . number_format((float) $maks, 0, ',', '.') . ' per transaksi.', 422, ['batas' => 'maksimal']);
        }

        $awal = now()->startOfDay();
        $hariIni = DB::table('penarikan')
            ->where('nasabah_id', $nasabah->id)->where('created_at', '>=', $awal)->whereIn('status', ['pending', 'berhasil']);

        if ($hariIni->count() >= $this->pengaturan->int('penarikan_maks_per_hari')) {
            throw new ApiException('BATAS_PENARIKAN', 'Batas jumlah penarikan hari ini sudah tercapai.', 422, ['batas' => 'jumlah_harian']);
        }
        $totalHariIni = (string) ($hariIni->sum('nominal') ?? '0');
        if (bccomp(bcadd($totalHariIni, $nominal, 2), $this->pengaturan->get('penarikan_total_per_hari'), 2) > 0) {
            throw new ApiException('BATAS_PENARIKAN', 'Batas total penarikan hari ini terlampaui.', 422, ['batas' => 'total_harian']);
        }

        $sisa = bcsub($nasabah->saldo, $nominal, 2);
        if (bccomp($sisa, '0', 2) < 0) {
            throw new ApiException('SALDO_KURANG', 'Saldo tidak cukup.');
        }
        if (bccomp($sisa, $this->pengaturan->get('saldo_min_tersisa'), 2) < 0) {
            throw new ApiException('SALDO_MINIM_TERSISA', 'Saldo yang tersisa harus minimal Rp' . number_format((float) $this->pengaturan->get('saldo_min_tersisa'), 0, ',', '.') . '.');
        }
    }

    /** Dipanggil webhook atau pengecekan berkala. Aman dipanggil berulang (idempoten). */
    public function selesaikan(string $ref, string $status): bool
    {
        return DB::transaction(function () use ($ref, $status) {
            $p = DB::table('penarikan')->where('ref_gateway', $ref)->lockForUpdate()->first();
            if (!$p) {
                return false;
            }
            if ($p->status !== 'pending') {
                return true; // sudah diproses sebelumnya
            }

            if ($status === 'berhasil') {
                DB::table('penarikan')->where('id', $p->id)->update(['status' => 'berhasil', 'updated_at' => now()]);
            } elseif ($status === 'gagal') {
                DB::table('penarikan')->where('id', $p->id)->update(['status' => 'gagal', 'updated_at' => now()]);
                $this->saldo->catat($p->nasabah_id, 'refund', 'kredit', $p->nominal, 'penarikan', $p->id, null, 'Penarikan #' . $p->id . ' gagal');
                $this->audit->catat(null, 'penarikan.gagal_refund', 'penarikan', null, ['penarikan_id' => $p->id]);
            }

            return true;
        });
    }
}
