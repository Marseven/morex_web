<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BudgetCycle extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'period_name',
        'total_budget',
        'total_spent',
        'status',
        'trigger_transaction_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_budget' => 'integer',
        'total_spent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function triggerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'trigger_transaction_id');
    }

    /**
     * Récupère les transactions de ce cycle
     */
    public function getTransactions()
    {
        $query = Transaction::where('user_id', $this->user_id)
            ->where('date', '>=', $this->start_date);

        if ($this->end_date) {
            $query->where('date', '<=', $this->end_date);
        }

        return $query;
    }

    /**
     * Calcule le total dépensé pour ce cycle
     */
    public function calculateTotalSpent(): int
    {
        return $this->getTransactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    /**
     * Calcule le total des revenus pour ce cycle
     */
    public function calculateTotalIncome(): int
    {
        return $this->getTransactions()
            ->where('type', 'income')
            ->sum('amount');
    }

    /**
     * Met à jour les totaux du cycle
     */
    public function updateTotals(): void
    {
        $this->total_spent = $this->calculateTotalSpent();

        // Calculer le budget total depuis les catégories
        $this->total_budget = Category::where(function ($q) {
            $q->where('user_id', $this->user_id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        $this->save();
    }

    /**
     * Vérifie si le cycle est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Clôture le cycle
     */
    public function close(Carbon $endDate = null): void
    {
        $this->end_date = $endDate ?? now()->subDay();
        $this->status = 'closed';
        $this->updateTotals();
    }

    /**
     * Génère le nom de la période basé sur la date de début
     * Si le cycle commence avant le 15, c'est le mois courant
     * Sinon, c'est le mois suivant
     */
    public static function generatePeriodName(Carbon $startDate): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        // Si on commence après le 15, c'est le budget du mois suivant
        if ($startDate->day > 15) {
            $targetDate = $startDate->copy()->addMonth();
        } else {
            $targetDate = $startDate;
        }

        return $months[$targetDate->month] . ' ' . $targetDate->year;
    }
}
