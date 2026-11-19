<?php

namespace Database\Seeders;

use App\Services\Regions\RegionImporter;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $count = (new RegionImporter)->import();
        $this->command?->info('Referensi wilayah dimuat: '.$count.' kecamatan.');
    }
}
