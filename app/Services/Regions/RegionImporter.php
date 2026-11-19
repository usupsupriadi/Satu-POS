<?php

namespace App\Services\Regions;

use App\Models\Region;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memuat data referensi wilayah Indonesia dari database/data/regions.csv.
 *
 * Format CSV: province_code,province_name,regency_code,regency_name,district_code,district_name
 */
class RegionImporter
{
    private const CHUNK_SIZE = 500;

    public function path(): string
    {
        return database_path('data/regions.csv');
    }

    public function import(?string $path = null): int
    {
        $path ??= $this->path();

        if (! is_file($path)) {
            throw new RuntimeException('File referensi wilayah tidak ditemukan: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File referensi wilayah tidak bisa dibaca: '.$path);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return 0;
        }

        $columns = array_map(static fn ($value) => trim((string) $value), $header);
        $now = now();
        $buffer = [];
        $count = 0;

        DB::transaction(function () use ($handle, $columns, $now, &$buffer, &$count): void {
            while (($line = fgetcsv($handle)) !== false) {
                if (count($line) !== count($columns)) {
                    continue;
                }

                /** @var array<string, string> $row */
                $row = array_combine($columns, array_map(static fn ($value) => trim((string) $value), $line));

                if (($row['district_code'] ?? '') === '' || ($row['district_name'] ?? '') === '') {
                    continue;
                }

                $buffer[] = [
                    'province_code' => $row['province_code'] ?? '',
                    'province_name' => $row['province_name'] ?? '',
                    'regency_code' => $row['regency_code'] ?? '',
                    'regency_name' => $row['regency_name'] ?? '',
                    'district_code' => $row['district_code'],
                    'district_name' => $row['district_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;

                if (count($buffer) >= self::CHUNK_SIZE) {
                    $this->flush($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $this->flush($buffer);
                $buffer = [];
            }
        });

        fclose($handle);

        return $count;
    }

    private function flush(array $rows): void
    {
        Region::query()->upsert(
            $rows,
            ['district_code'],
            [
                'province_code',
                'province_name',
                'regency_code',
                'regency_name',
                'district_name',
                'updated_at',
            ],
        );
    }
}
