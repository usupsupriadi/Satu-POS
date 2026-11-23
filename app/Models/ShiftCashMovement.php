<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftCashMovement extends Model
{
    protected $fillable = [
        'shift_session_id',
        'type',
        'amount',
        'category',
        'note',
        'created_by_user_id',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reversal_of_id' => 'integer',
        ];
    }

    public function shiftSession(): BelongsTo
    {
        return $this->belongsTo(ShiftSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function isReversed(): bool
    {
        return $this->relationLoaded('reversal')
            ? $this->reversal !== null
            : $this->reversal()->exists();
    }

    public function isIn(): bool
    {
        return $this->type === 'in';
    }

    public function isOut(): bool
    {
        return $this->type === 'out';
    }
}
