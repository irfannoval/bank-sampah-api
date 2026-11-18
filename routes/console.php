<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Kirim ulang payout yang belum mendapat referensi dan sinkronkan statusnya.
Schedule::command('penarikan:cek-pending')->everyFiveMinutes()->withoutOverlapping();
