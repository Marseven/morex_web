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
        'user_id',
        'amount',
        'type',
        'category_id',
        'account_id',
        'beneficiary',
        'description',
        'date',
        'transfer_to_account_id',
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
            'transfer' => -$this->amount,
            default => $this->amount,
        };
    }

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
            if ($transaction->transfer_to_account_id) {
                $transaction->transferToAccount->recalculateBalance();
            }
        });

        static::updated(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
            if ($transaction->transfer_to_account_id) {
                $transaction->transferToAccount->recalculateBalance();
            }
            if ($transaction->wasChanged('account_id')) {
                $originalAccountId = $transaction->getOriginal('account_id');
                Account::find($originalAccountId)?->recalculateBalance();
            }
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->account->recalculateBalance();
            if ($transaction->transfer_to_account_id) {
                $transaction->transferToAccount->recalculateBalance();
            }
        });
    }
}
