<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Mitra\MitraAppController;
use App\Http\Controllers\Nasabah\NasabahAppController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// Alamat akhir: /api/v1/...
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Dipanggil payment gateway (tanpa login, dijaga tanda tangan)
    Route::post('webhook/gateway/disbursement', [WebhookController::class, 'disbursement'])->middleware('throttle:120,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/saya', [AuthController::class, 'saya']);
        Route::post('auth/ubah-pin', [AuthController::class, 'ubahPin'])->middleware('throttle:10,1');

        // ---------------- ADMIN ----------------
        Route::prefix('admin')->group(function () {
            Route::middleware(RoleMiddleware::class . ':admin,admin_utama')->group(function () {
                Route::get('nasabah', [Admin\NasabahController::class, 'index']);
                Route::post('nasabah', [Admin\NasabahController::class, 'store']);
                Route::get('nasabah/{id}', [Admin\NasabahController::class, 'show'])->whereNumber('id');
                Route::patch('nasabah/{id}', [Admin\NasabahController::class, 'update'])->whereNumber('id');
                Route::post('nasabah/{id}/reset-pin', [Admin\NasabahController::class, 'resetPin'])->whereNumber('id');
                Route::get('nasabah/{id}/mutasi', [Admin\NasabahController::class, 'mutasi'])->whereNumber('id');
                Route::post('nasabah/{id}/kartu', [Admin\KartuController::class, 'cetak'])->whereNumber('id');

                Route::post('kartu/scan', [Admin\KartuController::class, 'scan']);
                Route::get('kartu', [Admin\KartuController::class, 'index']);
                Route::post('kartu/{id}/status', [Admin\KartuController::class, 'status'])->whereNumber('id');
                Route::post('kartu/{id}/ganti', [Admin\KartuController::class, 'ganti'])->whereNumber('id');

                Route::get('jenis-sampah', [Admin\JenisSampahController::class, 'index']);
                Route::post('jenis-sampah', [Admin\JenisSampahController::class, 'store']);
                Route::patch('jenis-sampah/{id}', [Admin\JenisSampahController::class, 'update'])->whereNumber('id');

                Route::get('setoran', [Admin\SetoranController::class, 'index']);
                Route::post('setoran/hitung', [Admin\SetoranController::class, 'hitung']);
                Route::post('setoran', [Admin\SetoranController::class, 'store'])->middleware(Idempotency::class);

                Route::get('mitra', [Admin\MitraController::class, 'index']);
                Route::post('mitra', [Admin\MitraController::class, 'store']);
                Route::patch('mitra/{id}', [Admin\MitraController::class, 'update'])->whereNumber('id');
                Route::post('mitra/{id}/reset-pin', [Admin\MitraController::class, 'resetPin'])->whereNumber('id');

                Route::get('penarikan', [Admin\PenarikanController::class, 'index']);
                Route::post('penarikan', [Admin\PenarikanController::class, 'store'])->middleware(Idempotency::class);

                Route::get('pencairan', [Admin\PencairanController::class, 'index']);
                Route::post('pencairan/{id}/setujui', [Admin\PencairanController::class, 'setujui'])->whereNumber('id');
                Route::post('pencairan/{id}/tolak', [Admin\PencairanController::class, 'tolak'])->whereNumber('id');
                Route::post('pencairan/{id}/bayar', [Admin\PencairanController::class, 'bayar'])->whereNumber('id');
                Route::get('pencairan/{id}/bukti', [Admin\PencairanController::class, 'bukti'])->whereNumber('id');

                Route::get('transaksi-mitra', [Admin\TransaksiMitraController::class, 'index']);
                Route::post('transaksi-mitra/{id}/batal', [Admin\TransaksiMitraController::class, 'batal'])->whereNumber('id');

                Route::get('laporan/{jenis}', [Admin\LaporanController::class, 'show']);
            });

            // Khusus admin utama
            Route::middleware(RoleMiddleware::class . ':admin_utama')->group(function () {
                Route::post('setoran/{id}/batal', [Admin\SetoranController::class, 'batal'])->whereNumber('id');
                Route::post('nasabah/{id}/koreksi', [Admin\NasabahController::class, 'koreksi'])->whereNumber('id')->middleware(Idempotency::class);
                Route::get('pengaturan', [Admin\SistemController::class, 'pengaturan']);
                Route::patch('pengaturan', [Admin\SistemController::class, 'ubahPengaturan']);
                Route::get('audit-log', [Admin\SistemController::class, 'auditLog']);
                Route::get('selisih-saldo', [Admin\SistemController::class, 'selisihSaldo']);
            });
        });

        // ---------------- TOKO MITRA ----------------
        Route::prefix('mitra')->middleware(RoleMiddleware::class . ':mitra')->group(function () {
            Route::get('beranda', [MitraAppController::class, 'beranda']);
            Route::post('transaksi/mulai', [MitraAppController::class, 'mulai']);
            Route::post('transaksi/{sesiId}/konfirmasi', [MitraAppController::class, 'konfirmasi'])->middleware(Idempotency::class);
            Route::get('transaksi', [MitraAppController::class, 'riwayat']);
            Route::post('transaksi/{id}/batal', [MitraAppController::class, 'batal'])->whereNumber('id');
            Route::get('pencairan', [MitraAppController::class, 'pencairan']);
            Route::post('pencairan', [MitraAppController::class, 'ajukanPencairan'])->middleware(Idempotency::class);
        });

        // ---------------- NASABAH ----------------
        Route::prefix('nasabah')->middleware(RoleMiddleware::class . ':nasabah')->group(function () {
            Route::get('beranda', [NasabahAppController::class, 'beranda']);
            Route::get('mutasi', [NasabahAppController::class, 'mutasi']);
            Route::get('setoran', [NasabahAppController::class, 'setoran']);
            Route::get('setoran/{id}', [NasabahAppController::class, 'setoranDetail'])->whereNumber('id');
            Route::get('toko-mitra', [NasabahAppController::class, 'tokoMitra']);
            Route::get('penarikan/biaya', [NasabahAppController::class, 'biayaPenarikan']);
            Route::post('penarikan/validasi-rekening', [NasabahAppController::class, 'validasiRekening']);
            Route::get('penarikan', [NasabahAppController::class, 'daftarPenarikan']);
            Route::post('penarikan', [NasabahAppController::class, 'ajukanPenarikan'])->middleware(Idempotency::class);
            Route::get('penarikan/{id}', [NasabahAppController::class, 'penarikan'])->whereNumber('id');
        });
    });
});
