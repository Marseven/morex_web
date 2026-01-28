<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'target_amount',
        'current_amount',
        'target_date',
        'account_id',
        'status',
        'color',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'current_amount' => 'integer',
            'target_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount === 0) {
            return 0;
        }
        return min(100, ($this->current_amount / $this->target_amount) * 100);
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, $this->target_amount - $this->current_amount);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->target_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->target_date, false));
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->current_amount >= $this->target_amount;
    }

    public function addAmount(int $amount): void
    {
        $this->current_amount += $amount;
        if ($this->current_amount >= $this->target_amount) {
            $this->status = 'completed';
        }
        $this->save();
    }
}
