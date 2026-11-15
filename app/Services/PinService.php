<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi PIN + penguncian akun.
 * PIN tidak pernah ditulis ke log atau respons.
 */
class PinService
{
    public function __construct(private PengaturanService $pengaturan)
    {
    }

    public function verifikasi(User $user, string $pin): void
    {
        // Hasil dihitung di dalam transaksi, error dilempar SETELAH commit.
        // Kalau dilempar di dalam, hitungan salah PIN ikut di-rollback.
        $hasil = DB::transaction(function () use ($user, $pin) {
            $u = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($u->terkunci_sampai && $u->terkunci_sampai->isFuture()) {
                return ['terkunci', $u->terkunci_sampai];
            }

            if (Hash::check($pin, $u->pin_hash)) {
                if ($u->pin_gagal > 0 || $u->terkunci_sampai) {
                    $u->forceFill(['pin_gagal' => 0, 'terkunci_sampai' => null])->save();
                }
                return ['ok'];
            }

            $maks = $this->pengaturan->int('pin_maks_gagal');
            $gagal = $u->pin_gagal + 1;

            if ($gagal >= $maks) {
                $sampai = now()->addMinutes($this->pengaturan->int('pin_kunci_menit'));
                $u->forceFill(['pin_gagal' => 0, 'terkunci_sampai' => $sampai])->save();
                return ['terkunci', $sampai];
            }

            $u->forceFill(['pin_gagal' => $gagal])->save();
            return ['salah', $maks - $gagal];
        });

        if ($hasil[0] === 'terkunci') {
            throw new ApiException(
                'AKUN_TERKUNCI',
                'Terlalu banyak salah PIN. Akun dikunci sementara.',
                423,
                ['terkunci_sampai' => $hasil[1]->toIso8601String()],
            );
        }

        if ($hasil[0] === 'salah') {
            throw new ApiException('PIN_SALAH', 'PIN salah.', 422, ['sisa_percobaan' => $hasil[1]]);
        }
    }
}
