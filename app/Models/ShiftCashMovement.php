<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'reversed_at',
        'reversed_by_user_id',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reversal_of_id' => 'integer',
            'reversed_at' => 'datetime',
            'reversed_by_user_id' => 'integer',
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

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
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
