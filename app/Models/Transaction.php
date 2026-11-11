<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
            'reconciled_at' => 'datetime',
            'occurrence_date' => 'date',
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

    public const STATUS_PENDING = 'pending_validation';

    /** Champs qui changent le solde rapproché : les modifier dépointe l'opération (module dette-qualite). */
    public const MATERIAL_FIELDS = ['amount', 'type', 'account_id', 'transfer_to_account_id', 'date', 'status'];

    /** Dépointe une opération rapprochée dont un champ matériel change ; vrai si elle vient d'être dépointée. */
    public function unpointIfMaterialChange(): bool
    {
        if ($this->reconciled_at === null || ! $this->isDirty(self::MATERIAL_FIELDS)) {
            return false;
        }
        $this->reconciled_at = null;

        return true;
    }

    /**
     * Opérations comptées dans les revenus et dépenses (module revenus-transit, RM-1) :
     * hors attente, hors écarts de rapprochement, hors catégories en transit. Le pro reste compté.
     */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_PENDING)
            ->where(fn (Builder $q) => $q->whereNull('category_id')->orWhere(fn (Builder $q) => $q
                ->whereNotIn('category_id', Category::RECONCILIATION_IDS)
                ->whereNotIn('category_id', self::categoryIdsOfNature(Category::NATURE_TRANSIT))));
    }

    /** Dépenses perso : hors pro (RM-2). À combiner avec counted() (BudgetCycle::transactions() l'applique déjà). */
    public function scopePersonal(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('category_id')
                ->orWhereNotIn('category_id', self::categoryIdsOfNature(Category::NATURE_PRO)));
    }

    public function scopeOfNature(Builder $query, string $nature): Builder
    {
        return $query->whereIn('category_id', self::categoryIdsOfNature($nature));
    }

    /** Sous-requête : une catégorie supprimée garde sa nature pour les opérations passées. */
    private static function categoryIdsOfNature(string $nature): Builder
    {
        return Category::withTrashed()->select('id')->where('nature', $nature);
    }

    /**
     * Une transaction en attente de validation (import SMS) ne compte ni dans le
     * solde ni dans les cycles tant qu'elle n'est pas confirmée.
     */
    public function affectsBalance(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }

    private function affectedBalanceBeforeUpdate(): bool
    {
        return $this->getOriginal('status') !== self::STATUS_PENDING;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ').' FCFA';
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
        // Recalcule le cycle qui contient une date : totaux complets pour le cycle actif,
        // dépensé seul pour un cycle clôturé (son budget reste celui de la clôture). P12 : ancienne et nouvelle date.
        $refreshCycleAt = function (Transaction $transaction, $date) {
            if ($date === null || ! in_array($transaction->type, ['expense', 'income']) && ! in_array($transaction->getOriginal('type'), ['expense', 'income'])) {
                return;
            }
            BudgetCycle::where('user_id', $transaction->user_id)
                ->whereDate('start_date', '<=', $date)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
                ->get()
                ->each(fn (BudgetCycle $cycle) => $cycle->status === 'active' ? $cycle->updateTotals() : $cycle->refreshSpent());
        };
        $updateBudgetCycle = function (Transaction $transaction) use ($refreshCycleAt) {
            $refreshCycleAt($transaction, $transaction->date);
            if ($transaction->wasChanged('date')) {
                $refreshCycleAt($transaction, $transaction->getOriginal('date'));
            }
        };

        // Ne rien renvoyer : un false renvoyé par « updating » annulerait la mise à jour
        static::updating(function (Transaction $transaction): void {
            $transaction->unpointIfMaterialChange();
        });

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
            if (! $transaction->affectsBalance()) {
                return;
            }
            $applyAdjustments($transaction);
            $updateBudgetCycle($transaction);
        });

        static::updated(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments, $reverseOldAdjustments) {
            $relevantChanged = $transaction->wasChanged('amount')
                || $transaction->wasChanged('type')
                || $transaction->wasChanged('account_id')
                || $transaction->wasChanged('transfer_to_account_id');

            $wasCounted = $transaction->affectedBalanceBeforeUpdate();
            $isCounted = $transaction->affectsBalance();

            if ($wasCounted && $isCounted && $relevantChanged) {
                $reverseOldAdjustments($transaction);
                $applyAdjustments($transaction);
            } elseif (! $wasCounted && $isCounted) {
                // Validation d'une transaction en attente : premier passage au solde
                $applyAdjustments($transaction, desc: 'Validation transaction');
            } elseif ($wasCounted && ! $isCounted) {
                $reverseOldAdjustments($transaction);
            }

            $updateBudgetCycle($transaction);
        });

        static::deleted(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments) {
            if (! $transaction->affectsBalance()) {
                return;
            }
            $applyAdjustments($transaction, reverse: true, desc: 'Suppression transaction');
            $updateBudgetCycle($transaction);
        });

        static::restored(function (Transaction $transaction) use ($updateBudgetCycle, $applyAdjustments) {
            if (! $transaction->affectsBalance()) {
                return;
            }
            $applyAdjustments($transaction, desc: 'Restauration transaction');
            $updateBudgetCycle($transaction);
        });
    }
}
