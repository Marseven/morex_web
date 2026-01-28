<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'initial_amount',
        'current_amount',
        'due_date',
        'description',
        'contact_name',
        'contact_phone',
        'status',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'integer',
            'current_amount' => 'integer',
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->initial_amount === 0) {
            return 100;
        }
        $paid = $this->initial_amount - $this->current_amount;
        return min(100, ($paid / $this->initial_amount) * 100);
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, $this->current_amount);
    }

    public function getPaidAmountAttribute(): int
    {
        return $this->initial_amount - $this->current_amount;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }
        return now()->diffInDays($this->due_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        return $this->due_date->isPast() && $this->status === 'active';
    }

    public function addPayment(int $amount): void
    {
        $this->current_amount = max(0, $this->current_amount - $amount);
        if ($this->current_amount <= 0) {
            $this->status = 'paid';
        }
        $this->save();
    }
}
