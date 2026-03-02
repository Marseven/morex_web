<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetClosure extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', // Nécessaire pour updateOrCreate (lookup key)
        'year',
        'month',
        'total_income',
        'total_budget',
        'total_spent',
        'total_saved',
        'transaction_id',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'total_income' => 'integer',
        'total_budget' => 'integer',
        'total_spent' => 'integer',
        'total_saved' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Nom du mois formaté
     */
    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return $months[$this->month] . ' ' . $this->year;
    }
}
