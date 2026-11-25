<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Database Otomatis
    |--------------------------------------------------------------------------
    |
    | Arsip dibuat dengan utilitas resmi MySQL/MariaDB (mariadb-dump atau
    | mysqldump), dikompresi gzip, disimpan di storage/app/backups/database,
    | dan dijadwalkan tiap hari lewat scheduler.
    |
    */

    'enabled' => (bool) env('DB_BACKUP_ENABLED', true),

    'path' => env('DB_BACKUP_PATH', storage_path('app/backups/database')),

    // Jumlah arsip terbaru yang disimpan; arsip lebih lama dihapus otomatis.
    'keep' => (int) env('DB_BACKUP_KEEP', 14),

    // Jam backup harian (zona waktu APP_TIMEZONE).
    'schedule_time' => env('DB_BACKUP_SCHEDULE_TIME', '02:30'),

    // Batas waktu satu proses dump/restore dalam detik.
    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 1800),

    /*
    |--------------------------------------------------------------------------
    | Lokasi Binary
    |--------------------------------------------------------------------------
    |
    | Kosongkan agar lokasi dideteksi otomatis (PATH lalu folder instalasi
    | MariaDB/MySQL/XAMPP/Laragon). Isi bila binary berada di lokasi lain.
    |
    */

    'binaries' => [
        'dump' => env('DB_BACKUP_DUMP_BINARY'),
        'client' => env('DB_BACKUP_CLIENT_BINARY'),
    ],

];
