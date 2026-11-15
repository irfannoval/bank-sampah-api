<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class KartuService
{
    public function __construct(private AuditService $audit)
    {
    }

    private function token(): string
    {
        return bin2hex(random_bytes(24)); // 48 karakter acak
    }

    private function adaAktif(int $nasabahId): bool
    {
        return DB::table('kartu')->where('nasabah_id', $nasabahId)->where('status', 'aktif')->exists();
    }

    public function cetak(int $nasabahId, ?int $oleh): object
    {
        return DB::transaction(function () use ($nasabahId, $oleh) {
            $nasabah = DB::table('nasabah')->where('id', $nasabahId)->lockForUpdate()->first();
            if (!$nasabah) {
                throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
            }
            if ($this->adaAktif($nasabahId)) {
                throw new ApiException('KARTU_AKTIF_SUDAH_ADA', 'Nasabah sudah punya kartu aktif. Gunakan blokir atau ganti kartu.');
            }

            $token = $this->token();
            $id = DB::table('kartu')->insertGetId(['nasabah_id' => $nasabahId, 'qr_token' => $token]);
            $this->audit->catat($oleh, 'kartu.cetak', 'kartu', null, ['kartu_id' => $id, 'nasabah_id' => $nasabahId]);

            return (object) ['id' => $id, 'nasabah_id' => $nasabahId, 'qr_token' => $token];
        });
    }

    /** Ubah status kartu: aktif | diblokir | hilang. */
    public function ubahStatus(int $kartuId, string $status, ?int $oleh): void
    {
        DB::transaction(function () use ($kartuId, $status, $oleh) {
            $kartu = DB::table('kartu')->where('id', $kartuId)->lockForUpdate()->first();
            if (!$kartu) {
                throw new ApiException('KARTU_TIDAK_DITEMUKAN', 'Kartu tidak ditemukan.', 404);
            }
            if ($status === 'aktif' && $kartu->status !== 'aktif' && $this->adaAktif($kartu->nasabah_id)) {
                throw new ApiException('KARTU_AKTIF_SUDAH_ADA', 'Nasabah sudah punya kartu aktif lain.');
            }

            DB::table('kartu')->where('id', $kartuId)->update(['status' => $status]);
            $this->audit->catat($oleh, 'kartu.status', 'kartu', ['status' => $kartu->status], ['status' => $status, 'kartu_id' => $kartuId]);
        });
    }

    /** Ganti kartu hilang/rusak: kartu lama dinonaktifkan, kartu baru dengan QR baru dibuat. */
    public function ganti(int $kartuId, string $statusLama, ?int $oleh): object
    {
        return DB::transaction(function () use ($kartuId, $statusLama, $oleh) {
            $lama = DB::table('kartu')->where('id', $kartuId)->lockForUpdate()->first();
            if (!$lama) {
                throw new ApiException('KARTU_TIDAK_DITEMUKAN', 'Kartu tidak ditemukan.', 404);
            }

            if ($lama->status === 'aktif') {
                DB::table('kartu')->where('id', $kartuId)->update(['status' => $statusLama]);
            }
            if ($this->adaAktif($lama->nasabah_id)) {
                throw new ApiException('KARTU_AKTIF_SUDAH_ADA', 'Nasabah sudah punya kartu aktif lain.');
            }

            $token = $this->token();
            $baruId = DB::table('kartu')->insertGetId(['nasabah_id' => $lama->nasabah_id, 'qr_token' => $token]);
            DB::table('kartu')->where('id', $kartuId)->update(['kartu_pengganti_id' => $baruId]);
            $this->audit->catat($oleh, 'kartu.ganti', 'kartu', ['kartu_lama' => $kartuId], ['kartu_baru' => $baruId]);

            return (object) ['id' => $baruId, 'nasabah_id' => $lama->nasabah_id, 'qr_token' => $token];
        });
    }
}
