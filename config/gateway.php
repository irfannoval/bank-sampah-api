<?php

return [
    // 'fake' hanya untuk pengembangan; 'midtrans' memakai Midtrans Iris untuk payout bank.
    'driver' => env('GATEWAY_DRIVER', 'fake'),

    'midtrans' => [
        'iris_api_key' => env('MIDTRANS_IRIS_API_KEY', ''),
        'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
        'timeout' => env('MIDTRANS_TIMEOUT', 15),
    ],

    // Dipakai endpoint webhook simulasi; Midtrans Iris memakai polling status.
    'webhook_secret' => env('GATEWAY_WEBHOOK_SECRET', ''),
];
