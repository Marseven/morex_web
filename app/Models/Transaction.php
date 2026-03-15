<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'amount',
        'type',
        'category_id',
        'account_id',
        'beneficiary',
        'description',
        'date',
        'transfer_to_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transferToAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_to_account_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ') . ' FCFA';
    }

    public function getSignedAmountAttribute(): int
    {
        return match ($this->type) {
            'income' => $this->amount,
            'expense' => -$this->amount,
            default => $this->amount,
        };
    }

    protected static function booted(): void
    {
        $updateBudgetCycle = function (Transaction $transaction) {
            if (in_array($transaction->type, ['expense', 'income'])) {
                $activeCycle = BudgetCycle::where('user_id', $transaction->user_id)
                    ->where('status', 'active')
                    ->whereDate('start_date', '<=', $transaction->date)
                    ->where(function ($q) use ($transaction) {
                        $q->whereNull('end_date')
                          ->orWhereDate('end_date', '>=', $transaction->date);
                    })
                    ->first();
                $activeCycle?->updateTotals();
            }
        };

        static::created(function (Transaction $transaction) use ($updateBudgetCycle) {
            $transaction->account->recalculateBalance();
            $updateBudgetCycle($transaction);
        });

        static::updated(function (Transaction $transaction) use ($updateBudgetCycle) {
            $transaction->account->recalculateBalance();
            if ($transaction->wasChanged('account_id')) {
                $originalAccountId = $transaction->getOriginal('account_id');
                Account::find($originalAccountId)?->recalculateBalance();
            }
            $updateBudgetCycle($transaction);
        });

        static::deleted(function (Transaction $transaction) use ($updateBudgetCycle) {
            $transaction->account->recalculateBalance();
            $updateBudgetCycle($transaction);
        });

        static::restored(function (Transaction $transaction) use ($updateBudgetCycle) {
            $transaction->account->recalculateBalance();
            $updateBudgetCycle($transaction);
        });
    }
}
