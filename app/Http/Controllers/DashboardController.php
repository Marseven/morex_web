<?php

namespace App\Http\Controllers;

use App\Models\BudgetAlert;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\User;
use App\Services\BudgetAlertService;
use App\Services\GoalProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->get('period', 'month');

        // Calculate date range based on period
        $dateRange = $this->getDateRange($period);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Récupérer le cycle budgétaire actif pour les budgets
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        // Dates de la période budgétaire
        $budgetStartDate = $activeCycle?->start_date ?? now()->startOfMonth();
        $budgetEndDate = $activeCycle?->end_date;

        $accounts = $user->accounts()->orderBy('order_index')->get();
        $totalBalance = $accounts->sum('balance');

        // Period data
        $incomeForPeriod = $user->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        $expenseForPeriod = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        $periodVariation = $incomeForPeriod - $expenseForPeriod;

        // Transactions récentes
        $recentTransactions = $user->transactions()
            ->with(['category', 'account'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // Objectifs actifs
        $activeGoals = $user->goals()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();

        // Catégories avec budget pour la période budgétaire active

        // Plafonds (module plafonds-alertes) : même calcul que les alertes (hors en attente et écarts)
        $alerts = app(BudgetAlertService::class);
        [$capFrom, $capTo, $capIsCycle] = $alerts->period($user);

        $budgetCategories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('is_system', true);
        })
            ->whereNotNull('budget_limit')
            ->where('budget_limit', '>', 0)
            ->get()
            ->map(function ($category) use ($user, $alerts, $capFrom, $capTo) {
                $spent = $alerts->spent($user, $category->id, $capFrom, $capTo);
                $limit = (int) $category->budget_limit;
                $percent = (int) floor($spent * 100 / $limit);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'budget_limit' => $limit,
                    'spent' => $spent,
                    'progress' => min(100, $percent),
                    'percent' => $percent,
                    'status' => $percent >= 100 ? 'over' : ($percent >= 80 ? 'warning' : 'ok'),
                    'remaining' => $limit - $spent,
                ];
            })
            ->sortByDesc('percent')
            ->values();

        // Dettes actives
        $activeDebts = $user->debts()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();

        return Inertia::render('Dashboard', [
            'totalBalance' => $totalBalance,
            'periodVariation' => $periodVariation,
            'incomeForPeriod' => $incomeForPeriod,
            'expenseForPeriod' => $expenseForPeriod,
            'accounts' => $accounts,
            'recentTransactions' => $recentTransactions,
            'activeGoals' => $activeGoals,
            'savingsGoal' => $this->savingsGoal($user),
            'budgetPeriod' => ['start' => $capFrom->toDateString(), 'end' => $capTo?->toDateString(), 'is_cycle' => $capIsCycle],
            'budgetAlerts' => BudgetAlert::with('category:id,name')
                ->where('user_id', $user->id)
                ->where('cycle_start', '>=', $capFrom->toDateString())
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (BudgetAlert $a) => [
                    'id' => $a->id, 'category_id' => $a->category_id, 'category' => $a->category?->name,
                    'threshold' => $a->threshold, 'spent' => $a->spent, 'limit' => $a->limit,
                    'created_at' => $a->created_at?->toDateString(),
                ]),
            'activeDebts' => $activeDebts,
            'budgetCategories' => $budgetCategories,
            'currentPeriod' => $period,
            'periodLabel' => $this->getPeriodLabel($period),
        ]);
    }

    private function getDateRange(string $period): array
    {
        return match ($period) {
            'day' => [
                'start' => Carbon::today(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'week' => [
                'start' => Carbon::now()->startOfWeek(),
                'end' => Carbon::now()->endOfWeek(),
            ],
            'month' => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
            'year' => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            default => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
        };
    }

    private function getPeriodLabel(string $period): string
    {
        return match ($period) {
            'day' => "Aujourd'hui",
            'week' => 'Cette semaine',
            'month' => 'Ce mois',
            'year' => 'Cette année',
            default => 'Ce mois',
        };
    }

    /** Carte « Objectif » : le premier objectif actif suivi sur un compte (module suivi-objectifs). */
    private function savingsGoal(User $user): ?array
    {
        $goal = $user->goals()->where('status', 'active')->whereNotNull('tracked_since')->with('account')->orderBy('target_date')->first();

        return $goal ? app(GoalProgressService::class)->status($goal) : null;
    }
}
