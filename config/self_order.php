<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masa berlaku QR meja (hari)
    |--------------------------------------------------------------------------
    |
    | Link QR ditandatangani (HMAC) dan kedaluwarsa. Setelah lewat masa ini,
    | foto QR lama tidak bisa dipakai lagi dan QR perlu dicetak ulang
    | (jalankan `php artisan qr:regenerate`).
    |
    */

    'qr_ttl_days' => (int) env('SELF_ORDER_QR_TTL_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Masa berlaku sesi meja (menit)
    |--------------------------------------------------------------------------
    |
    | Setelah scan QR, pelanggan hanya bisa memesan dalam jendela waktu ini.
    | Harus scan ulang di meja untuk memulai sesi baru. Isi 0 untuk mematikan.
    |
    */

    'table_session_minutes' => (int) env('SELF_ORDER_TABLE_SESSION_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Batas waktu pesanan tunai pending (menit)
    |--------------------------------------------------------------------------
    |
    | Pesanan self-order "bayar di kasir" yang tidak diambil kasir dalam
    | rentang ini otomatis dibatalkan oleh `self-order:expire-pending`.
    |
    */

    'pending_expiry_minutes' => (int) env('SELF_ORDER_PENDING_EXPIRY_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Izinkan QR lama tanpa tanda tangan
    |--------------------------------------------------------------------------
    |
    | Hanya untuk masa transisi saat mencetak ulang QR meja. Biarkan false
    | agar QR harus bertanda tangan dan belum kedaluwarsa.
    |
    */

    'allow_legacy_unsigned_qr' => (bool) env('SELF_ORDER_ALLOW_LEGACY_QR', false),

];
