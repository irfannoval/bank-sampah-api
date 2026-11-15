<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * SATU-SATUNYA pintu perubahan saldo nasabah.
 * Mengunci baris nasabah, mengubah saldo, dan mencatat mutasi_saldo
 * dalam transaksi yang sama.
 */
class SaldoService
{
    public function catat(
        int $nasabahId,
        string $tipe,   // setoran|belanja|penarikan|refund|koreksi
        string $arah,   // kredit|debit
        string $nominal,
        ?string $refTipe = null,
        ?int $refId = null,
        ?int $dibuatOleh = null,
        ?string $keterangan = null,
    ): object {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('SaldoService::catat harus dipanggil di dalam DB::transaction');
        }

        if (!in_array($arah, ['kredit', 'debit'], true)) {
            throw new LogicException("Arah tidak dikenal: {$arah}");
        }

        if (bccomp($nominal, '0', 2) <= 0) {
            throw new ApiException('NOMINAL_TIDAK_VALID', 'Nominal harus lebih dari 0.');
        }

        $nasabah = DB::table('nasabah')->where('id', $nasabahId)->lockForUpdate()->first();
        if (!$nasabah) {
            throw new ApiException('NASABAH_TIDAK_DITEMUKAN', 'Nasabah tidak ditemukan.', 404);
        }

        $sebelum = $nasabah->saldo;
        $sesudah = $arah === 'kredit'
            ? bcadd($sebelum, $nominal, 2)
            : bcsub($sebelum, $nominal, 2);

        if (bccomp($sesudah, '0', 2) < 0) {
            throw new ApiException('SALDO_KURANG', 'Saldo tidak cukup.');
        }

        DB::table('nasabah')->where('id', $nasabahId)->update(['saldo' => $sesudah]);

        $id = DB::table('mutasi_saldo')->insertGetId([
            'nasabah_id' => $nasabahId,
            'tipe' => $tipe,
            'arah' => $arah,
            'nominal' => $nominal,
            'saldo_sebelum' => $sebelum,
            'saldo_sesudah' => $sesudah,
            'ref_tipe' => $refTipe,
            'ref_id' => $refId,
            'keterangan' => $keterangan,
            'dibuat_oleh' => $dibuatOleh,
        ]);

        return (object) [
            'id' => $id,
            'saldo_sebelum' => $sebelum,
            'saldo_sesudah' => $sesudah,
        ];
    }
}
