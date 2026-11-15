<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Membaca batas aturan bisnis dari tabel pengaturan_sistem. */
class PengaturanService
{
    public function get(string $kunci): string
    {
        $nilai = DB::table('pengaturan_sistem')->where('kunci', $kunci)->value('nilai');

        if ($nilai === null) {
            throw new RuntimeException("Pengaturan '{$kunci}' tidak ditemukan");
        }

        return (string) $nilai;
    }

    public function int(string $kunci): int
    {
        return (int) $this->get($kunci);
    }
}
