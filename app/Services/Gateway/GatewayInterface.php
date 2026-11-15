<?php

namespace App\Services\Gateway;

/**
 * Kontrak payment gateway (disbursement). Buat satu kelas per provider
 * lalu daftarkan di AppServiceProvider dan config/gateway.php.
 */
interface GatewayInterface
{
    /** @return array{nama: string} nama pemilik rekening/e-wallet. Lempar GatewayException jika tidak valid. */
    public function validasiRekening(string $tipe, string $nomor): array;

    /**
     * Kirim dana ke nasabah.
     * @return array{ref: string, status: string} status: pending|berhasil|gagal
     */
    public function kirim(object $penarikan): array;

    /** @return string pending|berhasil|gagal */
    public function cekStatus(string $ref): string;
}
