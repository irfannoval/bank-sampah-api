<?php

namespace App\Services\Gateway;

/**
 * Kontrak payment gateway (disbursement). Buat satu kelas per provider
 * lalu daftarkan di AppServiceProvider dan config/gateway.php.
 */
interface GatewayInterface
{
    /** @return list<array{code: string, name: string}> */
    public function daftarBank(): array;

    /** @return array{nama: string} nama pemilik rekening. */
    public function validasiRekening(string $bank, string $nomor): array;

    /**
     * Buat payout ke rekening penerima.
     *
     * @param  object{tujuan_bank: string, tujuan_nomor: string, nama_pemilik: string, nominal_dikirim: string, catatan: string}  $payout
     * @return array{ref: string, status: string} status: pending|berhasil|gagal
     */
    public function kirim(object $payout, string $idempotencyKey): array;

    /** @return string pending|berhasil|gagal */
    public function cekStatus(string $ref): string;
}
