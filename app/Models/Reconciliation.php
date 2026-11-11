<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rapprochement d'un compte avec son relevé : solde déclaré, solde Morex avant, écart et
 * opération d'écart éventuelle. Non synchronisé avec le mobile.
 */
class Reconciliation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'account_id',
        'statement_balance',
        'balance_before',
        'difference',
        'adjustment_transaction_id',
        'reconciled_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'statement_balance' => 'integer',
            'balance_before' => 'integer',
            'difference' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }
}
