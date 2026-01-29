<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetCycle;
use App\Models\BudgetSettings;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetCycleController extends Controller
{
    /**
     * Récupère les paramètres de cycle budgétaire
     */
    public function getSettings(Request $request): JsonResponse
    {
        $settings = BudgetSettings::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'preferred_start_day' => 1,
                'tolerance_start_day' => 25,
                'tolerance_end_day' => 31,
                'auto_detect_salary' => true,
            ]
        );

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Met à jour les paramètres de cycle budgétaire
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_start_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'tolerance_start_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'tolerance_end_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'salary_category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'salary_account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'auto_detect_salary' => ['sometimes', 'boolean'],
        ]);

        $settings = BudgetSettings::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json([
            'settings' => $settings,
            'message' => 'Paramètres mis à jour avec succès.',
        ]);
    }

    /**
     * Liste les cycles budgétaires
     */
    public function index(Request $request): JsonResponse
    {
        $cycles = BudgetCycle::where('user_id', $request->user()->id)
            ->orderBy('start_date', 'desc')
            ->limit(12)
            ->get();

        $activeCycle = $cycles->firstWhere('status', 'active');

        // Si pas de cycle actif, en créer un automatiquement
        if (!$activeCycle) {
            $activeCycle = $this->createDefaultCycle($request->user());
        } else {
            // Mettre à jour les totaux du cycle actif
            $activeCycle->updateTotals();
        }

        return response()->json([
            'active_cycle' => $activeCycle,
            'cycles' => $cycles,
        ]);
    }

    /**
     * Récupère le cycle actif avec détails
     */
    public function active(Request $request): JsonResponse
    {
        $cycle = BudgetCycle::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$cycle) {
            $cycle = $this->createDefaultCycle($request->user());
        } else {
            $cycle->updateTotals();
        }

        // Récupérer les dépenses par catégorie pour ce cycle
        $categoriesWithSpent = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->withSum(['transactions as spent_this_cycle' => function ($q) use ($cycle) {
            $q->where('type', 'expense')
              ->where('date', '>=', $cycle->start_date);
            if ($cycle->end_date) {
                $q->where('date', '<=', $cycle->end_date);
            }
        }], 'amount')
        ->get();

        return response()->json([
            'cycle' => $cycle,
            'categories' => $categoriesWithSpent,
            'days_remaining' => $this->calculateDaysRemaining($cycle),
            'daily_budget' => $this->calculateDailyBudget($cycle),
        ]);
    }

    /**
     * Démarre un nouveau cycle budgétaire
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'period_name' => ['sometimes', 'string', 'max:50'],
            'trigger_transaction_id' => ['nullable', 'uuid', 'exists:transactions,id'],
        ]);

        $user = $request->user();

        // Clôturer le cycle actif s'il existe
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now();

        if ($activeCycle) {
            $activeCycle->close($startDate->copy()->subDay());
        }

        // Créer le nouveau cycle
        $periodName = $validated['period_name'] ?? BudgetCycle::generatePeriodName($startDate);

        $totalBudget = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        $newCycle = BudgetCycle::create([
            'user_id' => $user->id,
            'start_date' => $startDate,
            'period_name' => $periodName,
            'total_budget' => $totalBudget,
            'total_spent' => 0,
            'status' => 'active',
            'trigger_transaction_id' => $validated['trigger_transaction_id'] ?? null,
        ]);

        return response()->json([
            'cycle' => $newCycle,
            'message' => "Nouveau cycle '{$periodName}' démarré avec succès.",
        ]);
    }

    /**
     * Clôture le cycle actif
     */
    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'end_date' => ['sometimes', 'date'],
        ]);

        $cycle = BudgetCycle::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$cycle) {
            return response()->json([
                'message' => 'Aucun cycle actif à clôturer.',
            ], 404);
        }

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $cycle->close($endDate);

        return response()->json([
            'cycle' => $cycle,
            'message' => "Cycle '{$cycle->period_name}' clôturé avec succès.",
        ]);
    }

    /**
     * Vérifie si une transaction devrait déclencher un nouveau cycle
     */
    public function checkSalaryTrigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'uuid', 'exists:transactions,id'],
        ]);

        $user = $request->user();
        $settings = BudgetSettings::where('user_id', $user->id)->first();

        if (!$settings || !$settings->auto_detect_salary) {
            return response()->json(['should_trigger' => false]);
        }

        $transaction = \App\Models\Transaction::find($validated['transaction_id']);

        // Vérifier si c'est un revenu dans la fenêtre de tolérance
        if ($transaction->type !== 'income') {
            return response()->json(['should_trigger' => false]);
        }

        $transactionDate = Carbon::parse($transaction->date);
        if (!$settings->isInSalaryWindow($transactionDate)) {
            return response()->json(['should_trigger' => false]);
        }

        // Vérifier si c'est dans la catégorie/compte salaire (si configuré)
        $matchesCategory = !$settings->salary_category_id ||
            $transaction->category_id === $settings->salary_category_id;
        $matchesAccount = !$settings->salary_account_id ||
            $transaction->account_id === $settings->salary_account_id;

        if (!$matchesCategory || !$matchesAccount) {
            return response()->json(['should_trigger' => false]);
        }

        // Générer le nom de période suggéré
        $suggestedPeriodName = BudgetCycle::generatePeriodName($transactionDate);

        return response()->json([
            'should_trigger' => true,
            'suggested_period_name' => $suggestedPeriodName,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Crée un cycle par défaut pour un nouvel utilisateur
     */
    private function createDefaultCycle($user): BudgetCycle
    {
        $settings = BudgetSettings::where('user_id', $user->id)->first();
        $startDay = $settings ? $settings->preferred_start_day : 1;

        // Calculer la date de début du cycle actuel
        $today = now();
        if ($today->day >= $startDay) {
            $startDate = $today->copy()->setDay($startDay);
        } else {
            $startDate = $today->copy()->subMonth()->setDay(min($startDay, $today->copy()->subMonth()->daysInMonth));
        }

        $totalBudget = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        return BudgetCycle::create([
            'user_id' => $user->id,
            'start_date' => $startDate,
            'period_name' => BudgetCycle::generatePeriodName($startDate),
            'total_budget' => $totalBudget,
            'total_spent' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * Calcule les jours restants dans le cycle
     */
    private function calculateDaysRemaining(BudgetCycle $cycle): int
    {
        if ($cycle->end_date) {
            return 0;
        }

        $settings = BudgetSettings::where('user_id', $cycle->user_id)->first();
        $startDay = $settings ? $settings->preferred_start_day : 1;

        // Date de fin théorique = veille du jour de début du mois suivant
        $nextCycleStart = now();
        if (now()->day >= $startDay) {
            $nextCycleStart = now()->addMonth()->setDay(min($startDay, now()->addMonth()->daysInMonth));
        } else {
            $nextCycleStart = now()->setDay(min($startDay, now()->daysInMonth));
        }

        return max(0, now()->diffInDays($nextCycleStart, false));
    }

    /**
     * Calcule le budget journalier restant
     */
    private function calculateDailyBudget(BudgetCycle $cycle): int
    {
        $daysRemaining = $this->calculateDaysRemaining($cycle);
        if ($daysRemaining <= 0) {
            return 0;
        }

        $remaining = $cycle->total_budget - $cycle->total_spent;
        return max(0, (int) floor($remaining / $daysRemaining));
    }
}
