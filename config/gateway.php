<?php

return [
    // 'fake' = simulasi untuk pengembangan. Ganti dengan driver provider asli
    // (Midtrans/Xendit/dll) setelah dipilih: buat kelas yang mengimplementasi GatewayInterface.
    'driver' => env('GATEWAY_DRIVER', 'fake'),

    // Rahasia untuk memverifikasi tanda tangan webhook (HMAC SHA-256).
    'webhook_secret' => env('GATEWAY_WEBHOOK_SECRET', ''),
];
