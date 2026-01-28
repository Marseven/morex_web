<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->get('period', 'month');
        $year = $request->get('year', now()->year);

        // Monthly data for charts
        $monthlyData = $this->getMonthlyData($user, $year);

        // Category breakdown for period
        $categoryBreakdown = $this->getCategoryBreakdown($user, $period);

        // Goals progress
        $goalsProgress = $user->goals()
            ->where('status', 'active')
            ->get()
            ->map(fn($goal) => [
                'id' => $goal->id,
                'name' => $goal->name,
                'target' => $goal->target_amount,
                'current' => $goal->current_amount,
                'progress' => $goal->progress_percentage,
                'color' => $goal->color,
            ]);

        // Budget vs actual
        $budgetComparison = $this->getBudgetComparison($user);

        // Debt summary
        $debtSummary = $this->getDebtSummary($user);

        // Savings rate
        $savingsRate = $this->getSavingsRate($user, $period);

        return Inertia::render('Analytics/Index', [
            'monthlyData' => $monthlyData,
            'categoryBreakdown' => $categoryBreakdown,
            'goalsProgress' => $goalsProgress,
            'budgetComparison' => $budgetComparison,
            'debtSummary' => $debtSummary,
            'savingsRate' => $savingsRate,
            'currentPeriod' => $period,
            'currentYear' => $year,
            'availableYears' => range(now()->year - 2, now()->year),
        ]);
    }

    private function getMonthlyData($user, int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $startDate = Carbon::create($year, $m, 1)->startOfMonth();
            $endDate = Carbon::create($year, $m, 1)->endOfMonth();

            $income = $user->transactions()
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $expense = $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $months[] = [
                'month' => $startDate->format('M'),
                'monthFull' => $startDate->translatedFormat('F'),
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }
        return $months;
    }

    private function getCategoryBreakdown($user, string $period): array
    {
        $dateRange = $this->getDateRange($period);

        $transactions = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->with('category')
            ->get()
            ->groupBy('category_id');

        $total = $transactions->flatten()->sum('amount');

        return $transactions->map(function ($items, $categoryId) use ($total) {
            $category = $items->first()->category;
            $amount = $items->sum('amount');
            return [
                'id' => $categoryId,
                'name' => $category?->name ?? 'Sans catégorie',
                'color' => $category?->color ?? '#71717A',
                'amount' => $amount,
                'percentage' => $total > 0 ? round(($amount / $total) * 100, 1) : 0,
                'count' => $items->count(),
            ];
        })->sortByDesc('amount')->values()->toArray();
    }

    private function getBudgetComparison($user): array
    {
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->whereNotNull('budget_limit')
        ->where('budget_limit', '>', 0)
        ->get();

        return $categories->map(function ($category) use ($user) {
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
                'budget' => $category->budget_limit,
                'spent' => $spent,
                'remaining' => max(0, $category->budget_limit - $spent),
                'percentage' => $category->budget_limit > 0 
                    ? min(100, round(($spent / $category->budget_limit) * 100, 1))
                    : 0,
                'isOverBudget' => $spent > $category->budget_limit,
            ];
        })->values()->toArray();
    }

    private function getDebtSummary($user): array
    {
        $debts = $user->debts()->where('status', 'active')->get();

        return [
            'totalDebt' => $debts->where('type', 'debt')->sum('current_amount'),
            'totalCredit' => $debts->where('type', 'credit')->sum('current_amount'),
            'debtCount' => $debts->where('type', 'debt')->count(),
            'creditCount' => $debts->where('type', 'credit')->count(),
            'netPosition' => $debts->where('type', 'credit')->sum('current_amount') - $debts->where('type', 'debt')->sum('current_amount'),
            'overdueCount' => $debts->filter(fn($d) => $d->is_overdue)->count(),
        ];
    }

    private function getSavingsRate($user, string $period): array
    {
        $dateRange = $this->getDateRange($period);

        $income = $user->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        $expense = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        $savings = $income - $expense;
        $rate = $income > 0 ? round(($savings / $income) * 100, 1) : 0;

        return [
            'income' => $income,
            'expense' => $expense,
            'savings' => $savings,
            'rate' => $rate,
            'target' => 25, // Target savings rate (from objectives)
        ];
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

    public function export(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', 'month');
        $format = $request->get('format', 'csv');

        $dateRange = $this->getDateRange($period);

        $transactions = $user->transactions()
            ->with(['category', 'account'])
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->orderBy('date')
            ->get();

        if ($format === 'csv') {
            $filename = 'morex_transactions_' . now()->format('Y-m-d') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function() use ($transactions) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Type', 'Catégorie', 'Compte', 'Bénéficiaire', 'Montant', 'Description']);
                
                foreach ($transactions as $tx) {
                    fputcsv($file, [
                        $tx->date->format('Y-m-d'),
                        $tx->type,
                        $tx->category?->name ?? 'Sans catégorie',
                        $tx->account?->name ?? '',
                        $tx->beneficiary,
                        $tx->amount,
                        $tx->description,
                    ]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return back()->with('error', 'Format non supporté.');
    }
}
