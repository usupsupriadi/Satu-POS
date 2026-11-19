<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Referensi wilayah administratif Indonesia (provinsi, kabupaten/kota, kecamatan).
 *
 * Tabel ini murni data referensi dan tidak boleh dipakai untuk wilayah sales.
 * Wilayah sales/poligon tetap memakai MemberRegion.
 */
class Region extends Model
{
    protected $fillable = [
        'province_code',
        'province_name',
        'regency_code',
        'regency_name',
        'district_code',
        'district_name',
    ];

    public static function provinceNames(): array
    {
        return static::query()
            ->distinct()
            ->orderBy('province_name')
            ->pluck('province_name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    public static function regencyNames(?string $province): array
    {
        $province = trim((string) $province);
        if ($province === '') {
            return [];
        }

        return static::query()
            ->where('province_name', $province)
            ->distinct()
            ->orderBy('regency_name')
            ->pluck('regency_name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    public static function districtNames(?string $province, ?string $regency): array
    {
        $province = trim((string) $province);
        $regency = trim((string) $regency);
        if ($province === '' || $regency === '') {
            return [];
        }

        return static::query()
            ->where('province_name', $province)
            ->where('regency_name', $regency)
            ->orderBy('district_name')
            ->pluck('district_name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    public static function isValidCombination(?string $province, ?string $regency, ?string $district): bool
    {
        $province = trim((string) $province);
        $regency = trim((string) $regency);
        $district = trim((string) $district);

        if ($province === '' || $regency === '' || $district === '') {
            return false;
        }

        return static::query()
            ->where('province_name', $province)
            ->where('regency_name', $regency)
            ->where('district_name', $district)
            ->exists();
    }
}
