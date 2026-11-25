<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
        {--connection= : Nama koneksi database (default dari DB_CONNECTION)}
        {--database= : Nama database yang dicadangkan (default dari koneksi)}
        {--path= : Folder tujuan arsip (default dari config backup.path)}
        {--keep= : Jumlah arsip terbaru yang disimpan (0 = jangan hapus)}';

    protected $description = 'Cadangkan database MySQL/MariaDB ke storage/app/backups/database';

    public function handle(): int
    {
        if (! config('backup.enabled')) {
            $this->components->warn('Backup dinonaktifkan (DB_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $service = new DatabaseBackupService($this->option('connection'));

        try {
            $meta = $service->dump(
                $this->option('database') ?: null,
                $this->option('path') ?: null
            );

            $keep = $this->option('keep');
            $keep = $keep === null ? (int) config('backup.keep', 14) : (int) $keep;
            $deleted = $service->prune($keep, dirname($meta['path']));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backup database '{$meta['database']}' selesai.");
        $this->line('  Arsip  : '.$meta['path']);
        $this->line('  Ukuran : '.Number::fileSize($meta['bytes'], precision: 2));
        $this->line('  Tabel  : '.$meta['tables']);
        $this->line('  SHA256 : '.$meta['sha256']);

        if ($deleted > 0) {
            $this->line('  Dibersihkan: '.$deleted.' berkas arsip lama');
        }

        return self::SUCCESS;
    }
}
