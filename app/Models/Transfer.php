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
        $recalcBothAccounts = function (Transfer $transfer) {
            $transfer->fromAccount?->recalculateBalance();
            $transfer->toAccount?->recalculateBalance();
        };

        static::created($recalcBothAccounts);

        static::updated(function (Transfer $transfer) use ($recalcBothAccounts) {
            $recalcBothAccounts($transfer);

            if ($transfer->wasChanged('from_account_id')) {
                Account::find($transfer->getOriginal('from_account_id'))?->recalculateBalance();
            }
            if ($transfer->wasChanged('to_account_id')) {
                Account::find($transfer->getOriginal('to_account_id'))?->recalculateBalance();
            }
        });

        static::deleted($recalcBothAccounts);
        static::restored($recalcBothAccounts);
    }
}
