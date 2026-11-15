<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Membuat satu akun admin utama untuk pengembangan.
     * GANTI PIN sebelum dipakai sungguhan (set SEED_ADMIN_PIN di .env).
     */
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['no_hp' => env('SEED_ADMIN_HP', '081200000000')],
            [
                'nama' => 'Admin Utama',
                'pin_hash' => Hash::make(env('SEED_ADMIN_PIN', '123456')),
                'role' => 'admin_utama',
                'status' => 'aktif',
            ],
        );
    }
}
