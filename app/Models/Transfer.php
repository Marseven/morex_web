<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'description',
        'date',
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

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    protected static function booted(): void
    {
        static::created(function (Transfer $transfer) {
            $transfer->fromAccount?->adjustBalance(-$transfer->amount, 'transfer_out', 'transfer', $transfer->id);
            $transfer->toAccount?->adjustBalance($transfer->amount, 'transfer_in', 'transfer', $transfer->id);
        });

        static::updated(function (Transfer $transfer) {
            $amountChanged = $transfer->wasChanged('amount');
            $fromChanged = $transfer->wasChanged('from_account_id');
            $toChanged = $transfer->wasChanged('to_account_id');

            if ($amountChanged || $fromChanged || $toChanged) {
                $oldAmount = $transfer->getOriginal('amount');
                $oldFromId = $transfer->getOriginal('from_account_id');
                $oldToId = $transfer->getOriginal('to_account_id');

                // Reverser l'ancien mouvement
                $oldFrom = $fromChanged ? Account::find($oldFromId) : $transfer->fromAccount;
                $oldTo = $toChanged ? Account::find($oldToId) : $transfer->toAccount;

                $oldFrom?->adjustBalance($oldAmount, 'transfer_in', 'transfer', $transfer->id, 'Correction: annulation ancien transfert');
                $oldTo?->adjustBalance(-$oldAmount, 'transfer_out', 'transfer', $transfer->id, 'Correction: annulation ancien transfert');

                // Appliquer le nouveau mouvement
                $transfer->fromAccount?->adjustBalance(-$transfer->amount, 'transfer_out', 'transfer', $transfer->id);
                $transfer->toAccount?->adjustBalance($transfer->amount, 'transfer_in', 'transfer', $transfer->id);
            }
        });

        static::deleted(function (Transfer $transfer) {
            // Reverser: remettre l'argent dans fromAccount, retirer de toAccount
            $transfer->fromAccount?->adjustBalance($transfer->amount, 'transfer_in', 'transfer', $transfer->id, 'Suppression transfert');
            $transfer->toAccount?->adjustBalance(-$transfer->amount, 'transfer_out', 'transfer', $transfer->id, 'Suppression transfert');
        });

        static::restored(function (Transfer $transfer) {
            $transfer->fromAccount?->adjustBalance(-$transfer->amount, 'transfer_out', 'transfer', $transfer->id, 'Restauration transfert');
            $transfer->toAccount?->adjustBalance($transfer->amount, 'transfer_in', 'transfer', $transfer->id, 'Restauration transfert');
        });
    }
}
