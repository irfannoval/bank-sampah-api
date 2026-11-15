<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('pengaturan_sistem')->insertOrIgnore([
            'kunci' => 'penarikan_biaya_admin',
            'nilai' => '2500',
            'keterangan' => 'Biaya admin penarikan (Rp), ditanggung nasabah. NILAI CONTOH: sesuaikan dengan fee payment gateway yang dipilih',
        ]);
    }

    public function down(): void
    {
        DB::table('pengaturan_sistem')->where('kunci', 'penarikan_biaya_admin')->delete();
    }
};
