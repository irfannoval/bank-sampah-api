<?php

namespace App\Services\Gateway;

use Illuminate\Support\Str;

/**
 * Simulasi untuk pengembangan dan tes. JANGAN dipakai di produksi.
 * - Nomor berakhiran 0000 dianggap tidak valid.
 * - Pengiriman selalu 'pending'; hasil datang lewat webhook atau cekStatus.
 */
class FakeGateway implements GatewayInterface
{
    public function validasiRekening(string $tipe, string $nomor): array
    {
        if (str_ends_with($nomor, '0000')) {
            throw new GatewayException('Rekening tidak ditemukan');
        }

        return ['nama' => 'PEMILIK ' . substr($nomor, -4)];
    }

    public function kirim(object $penarikan): array
    {
        return ['ref' => 'FAKE-' . Str::uuid(), 'status' => 'pending'];
    }

    public function cekStatus(string $ref): string
    {
        return 'berhasil';
    }
}
