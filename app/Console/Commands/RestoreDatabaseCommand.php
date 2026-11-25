<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'backup:restore
        {file? : Berkas arsip .sql/.sql.gz (boleh relatif terhadap folder backup)}
        {--latest : Pakai arsip backup terbaru}
        {--connection= : Nama koneksi database (default dari DB_CONNECTION)}
        {--database= : Database tujuan (default dari koneksi)}
        {--force : Wajib diisi; mengizinkan penimpaan isi database tujuan}';

    protected $description = 'Pulihkan database dari arsip backup ke database kosong/penuh';

    public function handle(): int
    {
        $file = $this->argument('file');
        $latest = (bool) $this->option('latest');

        if (! $file && ! $latest) {
            $this->components->error('Sebutkan berkas arsip atau gunakan --latest.');

            return self::FAILURE;
        }

        if ($file && $latest) {
            $this->components->error('Pilih salah satu: berkas arsip atau --latest.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->error('Restore menimpa isi database. Jalankan ulang dengan --force bila sudah yakin.');

            return self::FAILURE;
        }

        $service = new DatabaseBackupService($this->option('connection'));

        try {
            $archive = $latest ? $service->latestArchive() : (string) $file;
            $result = $service->restore($archive, $this->option('database') ?: null, true);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Restore ke database '{$result['database']}' selesai.");
        $this->line('  Arsip        : '.$result['path']);
        $this->line('  Tabel        : '.$result['tables']);
        $this->line('  Migrasi      : '.$result['migrations'].' baris');
        $this->line('  DB baru dibuat: '.($result['created_database'] ? 'ya' : 'tidak'));
        $this->newLine();
        $this->components->warn('Jalankan "php artisan optimize:clear" dan restart queue worker setelah restore.');

        return self::SUCCESS;
    }
}
