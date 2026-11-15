<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Skema inti dijalankan dari file SQL agar trigger dan view ikut terbawa. */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/001_init_bank_sampah.sql')));
    }

    public function down(): void
    {
        DB::unprepared('
            DROP VIEW IF EXISTS v_selisih_saldo;
            DROP TABLE IF EXISTS pengaturan_sistem, audit_log, mutasi_saldo, penarikan,
                transaksi_mitra, pencairan_mitra, detail_setoran, setoran, jenis_sampah,
                toko_mitra, kartu, nasabah, users CASCADE;
            DROP FUNCTION IF EXISTS tolak_ubah_hapus() CASCADE;
        ');
    }
};
