<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Pendaftaran nasabah/mitra dan reset PIN oleh admin. */
class AkunService
{
    public function __construct(private KartuService $kartu, private AuditService $audit)
    {
    }

    private function pinAcak(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** PIN awal ditampilkan SEKALI ke admin; nasabah diminta menggantinya. */
    public function daftarNasabah(array $data, int $adminId): array
    {
        return DB::transaction(function () use ($data, $adminId) {
            $pin = $data['pin'] ?? $this->pinAcak();

            $userId = DB::table('users')->insertGetId([
                'nama' => $data['nama'],
                'no_hp' => $data['no_hp'],
                'pin_hash' => Hash::make($pin),
                'role' => 'nasabah',
            ]);

            $nasabahId = DB::table('nasabah')->insertGetId([
                'user_id' => $userId,
                'kode_nasabah' => 'TMP-' . bin2hex(random_bytes(8)),
                'alamat' => $data['alamat'] ?? null,
            ]);
            $kode = sprintf('NSB-%05d', $nasabahId);
            DB::table('nasabah')->where('id', $nasabahId)->update(['kode_nasabah' => $kode]);

            $kartu = $this->kartu->cetak($nasabahId, $adminId);
            $this->audit->catat($adminId, 'nasabah.daftar', 'nasabah', null, ['nasabah_id' => $nasabahId, 'kode' => $kode]);

            return [
                'nasabah_id' => $nasabahId,
                'user_id' => $userId,
                'kode_nasabah' => $kode,
                'pin_awal' => $pin,
                'kartu' => ['id' => $kartu->id, 'qr_token' => $kartu->qr_token],
            ];
        });
    }

    public function daftarMitra(array $data, int $adminId): array
    {
        return DB::transaction(function () use ($data, $adminId) {
            $pin = $data['pin'] ?? $this->pinAcak();

            $userId = DB::table('users')->insertGetId([
                'nama' => $data['nama'],
                'no_hp' => $data['no_hp'],
                'pin_hash' => Hash::make($pin),
                'role' => 'mitra',
            ]);

            $mitraId = DB::table('toko_mitra')->insertGetId([
                'user_id' => $userId,
                'nama_toko' => $data['nama_toko'],
                'alamat' => $data['alamat'] ?? null,
                'bank_tujuan' => $data['bank_tujuan'] ?? null,
                'no_rekening' => $data['no_rekening'] ?? null,
                'atas_nama' => $data['atas_nama'] ?? null,
            ]);
            $this->audit->catat($adminId, 'mitra.daftar', 'toko_mitra', null, ['toko_mitra_id' => $mitraId]);

            return ['toko_mitra_id' => $mitraId, 'user_id' => $userId, 'pin_awal' => $pin];
        });
    }

    public function resetPin(int $userId, int $adminId): array
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user || !in_array($user->role, ['nasabah', 'mitra'], true)) {
            throw new ApiException('AKUN_TIDAK_DITEMUKAN', 'Akun tidak ditemukan.', 404);
        }

        $pin = $this->pinAcak();
        DB::table('users')->where('id', $userId)->update([
            'pin_hash' => Hash::make($pin), 'pin_gagal' => 0, 'terkunci_sampai' => null,
        ]);
        $this->audit->catat($adminId, 'akun.reset_pin', 'users', null, ['user_id' => $userId]);

        return ['pin_baru' => $pin];
    }
}
