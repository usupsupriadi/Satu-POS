<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Shift yang jam selesainya lebih kecil/sama dengan jam mulai dianggap
     * melewati tengah malam (mis. 22:00 - 06:00).
     */
    public function crossesMidnight(): bool
    {
        return $this->startTimeMinutes() >= $this->endTimeMinutes();
    }

    public function startTimeMinutes(): int
    {
        return self::toMinutes((string) $this->start_time);
    }

    public function endTimeMinutes(): int
    {
        return self::toMinutes((string) $this->end_time);
    }

    public function durationMinutes(): int
    {
        $duration = $this->endTimeMinutes() - $this->startTimeMinutes();

        return $duration > 0 ? $duration : $duration + 1440;
    }

    public function timeRangeLabel(): string
    {
        return $this->startLabel().' - '.$this->endLabel();
    }

    public function startLabel(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function endLabel(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function label(): string
    {
        return $this->name.' ('.$this->timeRangeLabel().')';
    }

    public static function toMinutes(string $time): int
    {
        $parts = explode(':', trim($time));
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return max(0, min(1439, ($hour * 60) + $minute));
    }
}
