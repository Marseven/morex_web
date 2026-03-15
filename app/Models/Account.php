<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'initial_balance',
        'balance',
        'color',
        'icon',
        'is_default',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'integer',
            'balance' => 'integer',
            'is_default' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function recalculateBalance(): void
    {
        $confirmedTransactions = $this->transactions()->where('status', 'confirmed');
        $income = (clone $confirmedTransactions)->where('type', 'income')->sum('amount');
        $expense = (clone $confirmedTransactions)->where('type', 'expense')->sum('amount');
        $transfersOut = $this->outgoingTransfers()->sum('amount');
        $transfersIn = $this->incomingTransfers()->sum('amount');

        $this->balance = $this->initial_balance + $income - $expense - $transfersOut + $transfersIn;
        $this->save();
    }

    /**
     * Set the real balance directly in DB. No recalculation.
     * The calculated balance (from transactions) is for analysis only.
     */
    public function adjustToBalance(int $desiredBalance): void
    {
        $this->initial_balance = $desiredBalance;
        $this->balance = $desiredBalance;
        $this->save();
    }
}
