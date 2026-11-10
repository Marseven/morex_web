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
            'tracked_since' => 'date',
            'tracked_start_amount' => 'integer',
            'tracked_account_balance' => 'integer',
            'monthly_target' => 'integer',
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

    public function isTracked(): bool
    {
        return $this->tracked_since !== null && $this->account_id !== null && $this->tracked_start_amount !== null;
    }

    /**
     * RM-1 : un objectif suivi tire sa progression du solde de son compte (départ + variation du solde),
     * jamais négative. Sinon, la valeur saisie (contributions manuelles).
     */
    public function getCurrentAmountAttribute(?int $value): int
    {
        if (! $this->isTracked()) {
            return (int) $value;
        }
        $balance = (int) Account::withTrashed()->whereKey($this->account_id)->value('balance');

        return max(0, $this->tracked_start_amount + $balance - (int) $this->tracked_account_balance);
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
        if (! $this->target_date) {
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
