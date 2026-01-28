<?php

namespace App\Http\Controllers;

use App\Models\Category;
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

        // Catégories avec budget pour le mois
        $budgetCategories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->whereNotNull('budget_limit')
        ->where('budget_limit', '>', 0)
        ->get()
        ->map(function ($category) use ($user) {
            $spent = $user->transactions()
                ->where('category_id', $category->id)
                ->where('type', 'expense')
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount');

            return [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'icon' => $category->icon,
                'budget_limit' => $category->budget_limit,
                'spent' => $spent,
                'progress' => $category->budget_limit > 0
                    ? min(100, ($spent / $category->budget_limit) * 100)
                    : 0,
            ];
        });

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
}
