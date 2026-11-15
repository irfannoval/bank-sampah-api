<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class SetoranService
{
    public function __construct(private SaldoService $saldo, private AuditService $audit)
    {
    }

    /**
     * Hitung total dari daftar [jenis_sampah_id, berat].
     * Harga SELALU diambil dari database, tidak pernah dari klien.
     */
    public function hitung(array $items): array
    {
        $ids = array_map(fn ($i) => (int) $i['jenis_sampah_id'], $items);
        $jenis = DB::table('jenis_sampah')->whereIn('id', $ids)->get()->keyBy('id');

        $baris = [];
        $totalBerat = '0.000';
        $totalNilai = '0.00';

        foreach ($items as $i) {
            $j = $jenis->get((int) $i['jenis_sampah_id']);
            if (!$j || !$j->aktif) {
                throw new ApiException('JENIS_SAMPAH_TIDAK_VALID', 'Jenis sampah tidak ditemukan atau tidak aktif.');
            }

            $berat = bcadd((string) $i['berat'], '0', 3);
            if (bccomp($berat, '0', 3) <= 0) {
                throw new ApiException('BERAT_TIDAK_VALID', 'Berat harus lebih dari 0.');
            }

            // Pembulatan ke 2 desimal (setengah ke atas)
            $subtotal = bcadd(bcmul($berat, $j->harga_per_kg, 5), '0.005', 2);

            $baris[] = [
                'jenis_sampah_id' => $j->id,
                'nama' => $j->nama,
                'berat' => $berat,
                'harga_per_kg' => $j->harga_per_kg,
                'subtotal' => $subtotal,
            ];
            $totalBerat = bcadd($totalBerat, $berat, 3);
            $totalNilai = bcadd($totalNilai, $subtotal, 2);
        }

        return ['baris' => $baris, 'total_berat' => $totalBerat, 'total_nilai' => $totalNilai];
    }

    public function simpan(int $adminId, int $nasabahId, ?int $kartuId, array $items): array
    {
        return DB::transaction(function () use ($adminId, $nasabahId, $kartuId, $items) {
            $nasabah = DB::table('nasabah')
                ->join('users', 'users.id', '=', 'nasabah.user_id')
                ->where('nasabah.id', $nasabahId)
                ->select('nasabah.id', 'users.status')
                ->first();

            if (!$nasabah) {
                throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
            }
            if ($nasabah->status !== 'aktif') {
                throw new ApiException('AKUN_NONAKTIF', 'Akun nasabah tidak aktif.');
            }

            if ($kartuId !== null) {
                $kartu = DB::table('kartu')->where('id', $kartuId)->where('nasabah_id', $nasabahId)->first();
                if (!$kartu || $kartu->status !== 'aktif') {
                    throw new ApiException('KARTU_TIDAK_AKTIF', 'Kartu tidak aktif.');
                }
            }

            $h = $this->hitung($items);

            $setoranId = DB::table('setoran')->insertGetId([
                'nasabah_id' => $nasabahId,
                'kartu_id' => $kartuId,
                'admin_id' => $adminId,
                'total_berat' => $h['total_berat'],
                'total_nilai' => $h['total_nilai'],
            ]);

            foreach ($h['baris'] as $b) {
                DB::table('detail_setoran')->insert([
                    'setoran_id' => $setoranId,
                    'jenis_sampah_id' => $b['jenis_sampah_id'],
                    'berat' => $b['berat'],
                    'harga_saat_itu' => $b['harga_per_kg'],
                    'subtotal' => $b['subtotal'],
                ]);
            }

            $mutasi = $this->saldo->catat(
                $nasabahId, 'setoran', 'kredit', $h['total_nilai'], 'setoran', $setoranId, $adminId, 'Setoran sampah'
            );

            return $h + ['setoran_id' => $setoranId, 'saldo_sesudah' => $mutasi->saldo_sesudah];
        });
    }

    /** Pembatalan setoran (hanya admin utama, dicek di rute). Dicatat sebagai mutasi koreksi. */
    public function batal(int $setoranId, int $adminId, string $alasan): array
    {
        return DB::transaction(function () use ($setoranId, $adminId, $alasan) {
            $s = DB::table('setoran')->where('id', $setoranId)->lockForUpdate()->first();
            if (!$s) {
                throw new ApiException('SETORAN_TIDAK_DITEMUKAN', 'Setoran tidak ditemukan.', 404);
            }
            if ($s->status !== 'berhasil') {
                throw new ApiException('SUDAH_DIBATALKAN', 'Setoran sudah dibatalkan.');
            }

            try {
                $mutasi = $this->saldo->catat(
                    $s->nasabah_id, 'koreksi', 'debit', $s->total_nilai, 'setoran', $s->id, $adminId,
                    'Pembatalan setoran #' . $s->id . ': ' . $alasan
                );
            } catch (ApiException $e) {
                if ($e->kode === 'SALDO_KURANG') {
                    throw new ApiException(
                        'SALDO_TIDAK_CUKUP_UNTUK_BATAL',
                        'Saldo nasabah sudah terpakai sehingga setoran tidak bisa dibatalkan otomatis. Gunakan koreksi saldo manual.'
                    );
                }
                throw $e;
            }

            DB::table('setoran')->where('id', $s->id)->update([
                'status' => 'dibatalkan', 'dibatalkan_oleh' => $adminId, 'alasan_batal' => $alasan, 'waktu_batal' => now(),
            ]);
            $this->audit->catat($adminId, 'setoran.batal', 'setoran', ['status' => 'berhasil'], ['setoran_id' => $s->id, 'alasan' => $alasan]);

            return ['setoran_id' => $s->id, 'saldo_sesudah' => $mutasi->saldo_sesudah];
        });
    }
}
