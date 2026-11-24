<?php

return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => (bool) env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => (bool) env('MIDTRANS_IS_3DS', true),

    /*
    |--------------------------------------------------------------------------
    | Biaya admin pembayaran online (persen)
    |--------------------------------------------------------------------------
    | Dipakai saat membuat tagihan QRIS self order. Sebelumnya nilai 0,7%
    | ditulis langsung di controller sehingga tidak bisa disesuaikan.
    */

    'fee_percent' => (float) env('MIDTRANS_FEE_PERCENT', 0.7),
];
