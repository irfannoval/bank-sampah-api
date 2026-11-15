<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Catat aksi penting. JANGAN pernah memasukkan PIN atau pin_hash ke sini. */
class AuditService
{
    public function catat(?int $userId, string $aksi, ?string $tabel = null, mixed $lama = null, mixed $baru = null): void
    {
        DB::table('audit_log')->insert([
            'user_id' => $userId,
            'aksi' => $aksi,
            'tabel' => $tabel,
            'data_lama' => $lama === null ? null : json_encode($lama),
            'data_baru' => $baru === null ? null : json_encode($baru),
        ]);
    }
}
