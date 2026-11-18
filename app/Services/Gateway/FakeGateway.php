<?php

namespace App\Services\Gateway;

use Illuminate\Support\Str;

/**
 * Simulasi untuk pengembangan dan tes. JANGAN dipakai di produksi.
 * - Nomor berakhiran 0000 dianggap tidak valid.
 * - Payout selalu 'pending'; hasil datang lewat webhook simulasi atau cekStatus.
 */
class FakeGateway implements GatewayInterface
{
    public function daftarBank(): array
    {
        return [
            ['code' => 'bca', 'name' => 'BCA'],
            ['code' => 'mandiri', 'name' => 'Mandiri'],
        ];
    }

    public function validasiRekening(string $bank, string $nomor): array
    {
        if (! in_array($bank, ['bca', 'mandiri'], true) || str_ends_with($nomor, '0000')) {
            throw new GatewayAccountException('Rekening tidak ditemukan');
        }

        return ['nama' => 'PEMILIK '.substr($nomor, -4)];
    }

    public function kirim(object $payout, string $idempotencyKey): array
    {
        return ['ref' => 'FAKE-'.Str::uuid(), 'status' => 'pending'];
    }

    public function cekStatus(string $ref): string
    {
        return 'berhasil';
    }
}
