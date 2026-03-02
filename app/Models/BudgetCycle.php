<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class BudgetCycle extends Model
{
    use HasUuids;

    protected $fillable = [
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
     * Récupère les transactions de ce cycle (retourne un Builder)
     */
    public function transactions()
    {
        $query = Transaction::where('user_id', $this->user_id)
            ->where('date', '>=', $this->start_date);

        if ($this->end_date) {
            // end_date stocke le dernier jour INCLUS du cycle
            // Donc on utilise <= pour l'inclure
            $query->where('date', '<=', $this->end_date);
        }

        return $query;
    }

    /**
     * Calcule le total dépensé pour ce cycle
     */
    public function calculateTotalSpent(): int
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    /**
     * Calcule le total des revenus pour ce cycle
     */
    public function calculateTotalIncome(): int
    {
        return $this->transactions()
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
     * Crée un snapshot (BudgetClosure) pour ce cycle clôturé
     */
    public function createClosure(): BudgetClosure
    {
        $totalIncome = $this->calculateTotalIncome();
        $totalSpent = $this->calculateTotalSpent();
        $totalSaved = $totalIncome - $totalSpent;

        $totalBudget = Category::where(function ($q) {
            $q->where('user_id', $this->user_id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        // Détails par catégorie
        $categories = Category::where(function ($q) {
            $q->where('user_id', $this->user_id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->get()
        ->map(function ($category) {
            $spent = $this->transactions()
                ->where('type', 'expense')
                ->where('category_id', $category->id)
                ->sum('amount');

            return [
                'id' => $category->id,
                'name' => $category->name,
                'budget_limit' => $category->budget_limit ?? 0,
                'spent' => (int) $spent,
            ];
        })
        ->filter(fn ($cat) => $cat['spent'] > 0 || $cat['budget_limit'] > 0)
        ->values()
        ->toArray();

        // Snapshot des soldes de comptes
        $accountsSnapshot = Account::where('user_id', $this->user_id)
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'balance' => $account->balance,
            ])
            ->toArray();

        $details = [
            'categories' => $categories,
            'accounts_snapshot' => $accountsSnapshot,
        ];

        // Déterminer l'année et le mois du cycle basé sur le DERNIER JOUR du cycle
        // end_date est le jour APRÈS la fin (ex: cycle Février terminé le 01/03 → closure pour Février)
        // Donc on prend end_date - 1 jour pour avoir le vrai dernier jour du cycle
        $lastDayOfCycle = $this->end_date ? $this->end_date->copy()->subDay() : now();

        $closure = BudgetClosure::updateOrCreate(
            [
                'user_id' => $this->user_id,
                'year' => $lastDayOfCycle->year,
                'month' => $lastDayOfCycle->month,
            ],
            [
                'total_income' => $totalIncome,
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'total_saved' => $totalSaved,
                'details' => $details,
            ]
        );

        // user_id n'est pas dans fillable (mass assignment protection)
        // On l'assigne manuellement pour les nouvelles insertions
        if ($closure->wasRecentlyCreated) {
            $closure->user_id = $this->user_id;
            $closure->save();
        }

        return $closure;
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
