<?php

namespace App\Console\Commands;

use App\Services\Regions\RegionImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportRegions extends Command
{
    protected $signature = 'regions:import {--file= : Path CSV referensi (default database/data/regions.csv)}';

    protected $description = 'Muat/segarkan referensi wilayah Indonesia (provinsi, kabupaten/kota, kecamatan)';

    public function handle(RegionImporter $importer): int
    {
        try {
            $count = $importer->import($this->option('file') ?: null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Referensi wilayah dimuat: '.$count.' kecamatan.');

        return self::SUCCESS;
    }
}
