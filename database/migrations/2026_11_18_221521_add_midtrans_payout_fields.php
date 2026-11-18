<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penarikan', function (Blueprint $table) {
            $table->string('tujuan_bank', 60)->nullable();
        });

        Schema::table('pencairan_mitra', function (Blueprint $table) {
            $table->string('bank_tujuan', 60)->nullable();
            $table->string('no_rekening', 40)->nullable();
            $table->string('atas_nama', 120)->nullable();
            $table->string('ref_gateway', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pencairan_mitra', function (Blueprint $table) {
            $table->dropColumn(['bank_tujuan', 'no_rekening', 'atas_nama', 'ref_gateway']);
        });

        Schema::table('penarikan', function (Blueprint $table) {
            $table->dropColumn('tujuan_bank');
        });
    }
};
