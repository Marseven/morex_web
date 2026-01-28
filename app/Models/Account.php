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
        'user_id',
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

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transfer_to_account_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function recalculateBalance(): void
    {
        $income = $this->transactions()->where('type', 'income')->sum('amount');
        $expense = $this->transactions()->where('type', 'expense')->sum('amount');
        $transfersOut = $this->transactions()->where('type', 'transfer')->sum('amount');
        $transfersIn = $this->incomingTransfers()->sum('amount');

        $this->balance = $this->initial_balance + $income - $expense - $transfersOut + $transfersIn;
        $this->save();
    }
}
