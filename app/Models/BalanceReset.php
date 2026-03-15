<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceReset extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id',
        'old_balance',
        'new_balance',
        'old_initial_balance',
        'reset_date',
    ];

    protected function casts(): array
    {
        return [
            'old_balance' => 'integer',
            'new_balance' => 'integer',
            'old_initial_balance' => 'integer',
            'reset_date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
