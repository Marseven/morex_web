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
            'expense', 'transfer' => -$this->amount,
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

        /**
         * Applique les ajustements de solde pour une transaction (source + destination pour les transferts).
         */
        $applyAdjustments = function (Transaction $transaction, bool $reverse = false, ?string $desc = null) {
            $amount = $transaction->amount;
            $sign = $reverse ? -1 : 1;

            match ($transaction->type) {
                'income' => $transaction->account->adjustBalance(
                    $amount * $sign,
                    $reverse ? 'expense' : 'income',
                    'transaction', $transaction->id, $desc
                ),
                'expense' => $transaction->account->adjustBalance(
                    -$amount * $sign,
                    $reverse ? 'income' : 'expense',
                    'transaction', $transaction->id, $desc
                ),
                'transfer' => (function () use ($transaction, $amount, $sign, $desc) {
                    // Débiter le compte source
                    $transaction->account->adjustBalance(
                        -$amount * $sign,
                        $sign > 0 ? 'transfer_out' : 'transfer_in',
                        'transaction', $transaction->id, $desc
                    );
                    // Créditer le compte destination
                    if ($transaction->transfer_to_account_id) {
                        $destAccount = Account::find($transaction->transfer_to_account_id);
                        $destAccount?->adjustBalance(
                            $amount * $sign,
                            $sign > 0 ? 'transfer_in' : 'transfer_out',
                            'transaction', $transaction->id, $desc
                        );
                    }
                })(),
                default => null,
            };
        };

        /**
         * Reverse les ajustements pour un ancien état (avant update).
         */
        $reverseOldAdjustments = function (Transaction $transaction) {
            $oldAmount = $transaction->getOriginal('amount');
            $oldType = $transaction->getOriginal('type');
            $oldAccountId = $transaction->getOriginal('account_id');
            $oldTransferToId = $transaction->getOriginal('transfer_to_account_id');

            $oldAccount = $transaction->wasChanged('account_id')
                ? Account::find($oldAccountId)
                : $transaction->account;

            match ($oldType) {
                'income' => $oldAccount?->adjustBalance(-$oldAmount, 'expense', 'transaction', $transaction->id, 'Correction: annulation ancien mouvement'),
                'expense' => $oldAccount?->adjustBalance($oldAmount, 'income', 'transaction', $transaction->id, 'Correction: annulation ancien mouvement'),
                'transfer' => (function () use ($oldAccount, $oldAmount, $oldTransferToId, $transaction) {
                    $oldAccount?->adjustBalance($oldAmount, 'transfer_in', 'transaction', $transaction->id, 'Correction: annulation ancien transfert');
                    if ($oldTransferToId) {
                        Account::find($oldTransferToId)?->adjustBalance(-$oldAmount, 'transfer_out', 'transaction', $transaction->id, 'Correction: annulation ancien transfert');
                    }
                })(),
                default => null,
            };
        };

        static::created(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments) {
            $applyAdjustments($transaction);
            $updateBudgetCycle($transaction);
        });

        static::updated(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments, $reverseOldAdjustments) {
            $relevantChanged = $transaction->wasChanged('amount')
                || $transaction->wasChanged('type')
                || $transaction->wasChanged('account_id')
                || $transaction->wasChanged('transfer_to_account_id');

            if ($relevantChanged) {
                $reverseOldAdjustments($transaction);
                $applyAdjustments($transaction);
            }

            $updateBudgetCycle($transaction);
        });

        static::deleted(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments) {
            $applyAdjustments($transaction, reverse: true, desc: 'Suppression transaction');
            $updateBudgetCycle($transaction);
        });

        static::restored(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments) {
            $applyAdjustments($transaction, desc: 'Restauration transaction');
            $updateBudgetCycle($transaction);
        });
    }
}
