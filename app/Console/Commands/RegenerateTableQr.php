<?php

namespace App\Console\Commands;

use App\Models\DiningTable;
use Illuminate\Console\Command;

class RegenerateTableQr extends Command
{
    protected $signature = 'qr:regenerate {--table= : ID meja tertentu (opsional)}';

    protected $description = 'Buat ulang gambar QR dan link self-order meja memakai APP_URL saat ini';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '') {
            $this->error('APP_URL belum diatur, link QR tidak bisa dibuat.');

            return self::FAILURE;
        }

        $query = DiningTable::query();

        $tableOption = $this->option('table');
        if ($tableOption !== null && $tableOption !== '') {
            $query->whereKey((int) $tableOption);
        }

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($tables) use (&$count): void {
            foreach ($tables as $table) {
                $table->touch();
                $count++;
            }
        });

        $this->info('QR & link '.$count.' meja dibuat ulang memakai '.$baseUrl.'.');

        return self::SUCCESS;
    }
}
