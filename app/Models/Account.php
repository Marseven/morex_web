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

    /**
     * Cascade applicative des soft-deletes.
     *
     * Les FK transactions.account_id / transfers.* sont déclarées `cascadeOnDelete` en base,
     * mais comme Account utilise SoftDeletes, la suppression est un simple UPDATE deleted_at :
     * la cascade SQL ne se déclenche jamais. On soft-delete donc explicitement les enfants
     * (et on les restaure au restore) pour éviter des transactions/transferts orphelins.
     */
    protected static function booted(): void
    {
        static::deleting(function (Account $account) {
            if ($account->isForceDeleting()) {
                return;
            }
            $account->transactions()->get()->each->delete();
            $account->outgoingTransfers()->get()->each->delete();
            $account->incomingTransfers()->get()->each->delete();
        });

        static::restoring(function (Account $account) {
            $account->transactions()->onlyTrashed()->get()->each->restore();
            $account->outgoingTransfers()->onlyTrashed()->get()->each->restore();
            $account->incomingTransfers()->onlyTrashed()->get()->each->restore();
        });
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

    public function balanceHistory(): HasMany
    {
        return $this->hasMany(BalanceHistory::class);
    }

    /**
     * Ajuste le solde du compte de manière incrémentale et crée une entrée dans le journal.
     *
     * @param int $delta Montant à ajouter (positif) ou soustraire (négatif)
     * @param string $type Type d'opération (income, expense, transfer_in, transfer_out, adjustment, creation)
     * @param string|null $refType Type de référence (transaction, transfer, manual)
     * @param string|null $refId UUID de la transaction/transfert source
     * @param string|null $desc Description optionnelle
     */
    public function adjustBalance(int $delta, string $type, ?string $refType = null, ?string $refId = null, ?string $desc = null): void
    {
        $balanceBefore = $this->balance;
        $newBalance = $this->balance + $delta;

        BalanceHistory::create([
            'account_id' => $this->id,
            'type' => $type,
            'amount' => abs($delta),
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $desc,
        ]);

        $this->balance = $newBalance;
        $this->save();
    }

    /**
     * Ajuste le solde réel du compte directement (ajustement manuel).
     * Crée une entrée d'ajustement dans le journal.
     */
    public function adjustToBalance(int $desiredBalance): void
    {
        $delta = $desiredBalance - $this->balance;

        if ($delta !== 0) {
            $this->adjustBalance($delta, 'adjustment', 'manual', null, 'Ajustement manuel du solde');
        }
    }
}
