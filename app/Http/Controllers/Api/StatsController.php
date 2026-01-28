<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StatsController extends Controller
{
    #[OA\Get(
        path: "/stats/dashboard",
        summary: "Dashboard principal - Statistiques globales",
        description: "Retourne toutes les statistiques nécessaires pour le dashboard de l'app mobile",
        tags: ["Stats"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques du dashboard",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "period", type: "object", properties: [
                            new OA\Property(property: "month", type: "string", example: "January 2026"),
                            new OA\Property(property: "start", type: "string", format: "date"),
                            new OA\Property(property: "end", type: "string", format: "date"),
                        ]),
                        new OA\Property(property: "balance", type: "object", properties: [
                            new OA\Property(property: "total", type: "integer", example: 1500000),
                            new OA\Property(property: "formatted", type: "string", example: "1 500 000 FCFA"),
                        ]),
                        new OA\Property(property: "monthly", type: "object", properties: [
                            new OA\Property(property: "income", type: "integer"),
                            new OA\Property(property: "expense", type: "integer"),
                            new OA\Property(property: "balance", type: "integer"),
                            new OA\Property(property: "savings_rate", type: "number"),
                            new OA\Property(property: "formatted_income", type: "string"),
                            new OA\Property(property: "formatted_expense", type: "string"),
                        ]),
                        new OA\Property(property: "goals", type: "object", properties: [
                            new OA\Property(property: "active_count", type: "integer"),
                            new OA\Property(property: "total_target", type: "integer"),
                            new OA\Property(property: "total_current", type: "integer"),
                            new OA\Property(property: "progress", type: "number"),
                        ]),
                        new OA\Property(property: "debts", type: "object", properties: [
                            new OA\Property(property: "total_debt", type: "integer"),
                            new OA\Property(property: "total_credit", type: "integer"),
                            new OA\Property(property: "net_position", type: "integer"),
                            new OA\Property(property: "overdue_count", type: "integer"),
                        ]),
                        new OA\Property(property: "alerts", type: "object", properties: [
                            new OA\Property(property: "exceeded_budgets", type: "integer"),
                            new OA\Property(property: "overdue_debts", type: "integer"),
                        ]),
                        new OA\Property(property: "top_expense_categories", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "color", type: "string"),
                                new OA\Property(property: "total", type: "integer"),
                                new OA\Property(property: "formatted_total", type: "string"),
                            ]
                        )),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Solde total des comptes
        $totalBalance = $user->accounts()->sum('balance');

        // Transactions du mois
        $monthlyTransactions = $user->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth]);

        $monthlyIncome = (clone $monthlyTransactions)->where('type', 'income')->sum('amount');
        $monthlyExpense = (clone $monthlyTransactions)->where('type', 'expense')->sum('amount');

        // Objectifs actifs
        $activeGoals = $user->goals()->where('status', 'active')->get();
        $totalGoalTarget = $activeGoals->sum('target_amount');
        $totalGoalCurrent = $activeGoals->sum('current_amount');

        // Dettes actives
        $activeDebts = $user->debts()->where('status', 'active')->get();
        $totalDebt = $activeDebts->where('type', 'debt')->sum('current_amount');
        $totalCredit = $activeDebts->where('type', 'credit')->sum('current_amount');

        // Taux d'épargne du mois
        $savingsRate = $monthlyIncome > 0
            ? round((($monthlyIncome - $monthlyExpense) / $monthlyIncome) * 100, 1)
            : 0;

        // Top catégories de dépenses du mois
        $topExpenseCategories = $user->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'name' => $item->category?->name ?? 'Sans catégorie',
                'color' => $item->category?->color ?? '#808080',
                'total' => $item->total,
                'formatted_total' => number_format($item->total, 0, ',', ' ') . ' FCFA',
            ]);

        // Budgets dépassés
        $exceededBudgets = $user->categories()
            ->where('type', 'expense')
            ->whereNotNull('budget_limit')
            ->where('budget_limit', '>', 0)
            ->get()
            ->filter(function ($category) use ($user, $startOfMonth, $endOfMonth) {
                $spent = $user->transactions()
                    ->where('category_id', $category->id)
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                return $spent > $category->budget_limit;
            })
            ->count();

        return response()->json([
            'period' => [
                'month' => now()->format('F Y'),
                'start' => $startOfMonth->toDateString(),
                'end' => $endOfMonth->toDateString(),
            ],
            'balance' => [
                'total' => $totalBalance,
                'formatted' => number_format($totalBalance, 0, ',', ' ') . ' FCFA',
            ],
            'monthly' => [
                'income' => $monthlyIncome,
                'expense' => $monthlyExpense,
                'balance' => $monthlyIncome - $monthlyExpense,
                'savings_rate' => $savingsRate,
                'formatted_income' => number_format($monthlyIncome, 0, ',', ' ') . ' FCFA',
                'formatted_expense' => number_format($monthlyExpense, 0, ',', ' ') . ' FCFA',
            ],
            'goals' => [
                'active_count' => $activeGoals->count(),
                'total_target' => $totalGoalTarget,
                'total_current' => $totalGoalCurrent,
                'progress' => $totalGoalTarget > 0
                    ? round(($totalGoalCurrent / $totalGoalTarget) * 100, 1)
                    : 0,
            ],
            'debts' => [
                'total_debt' => $totalDebt,
                'total_credit' => $totalCredit,
                'net_position' => $totalCredit - $totalDebt,
                'overdue_count' => $activeDebts->filter(fn($d) => $d->is_overdue)->count(),
            ],
            'alerts' => [
                'exceeded_budgets' => $exceededBudgets,
                'overdue_debts' => $activeDebts->filter(fn($d) => $d->is_overdue)->count(),
            ],
            'top_expense_categories' => $topExpenseCategories,
        ]);
    }

    #[OA\Get(
        path: "/stats/monthly",
        summary: "Statistiques mensuelles détaillées",
        tags: ["Stats"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "month", in: "query", schema: new OA\Schema(type: "string", example: "2026-01"), description: "Format YYYY-MM"),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques du mois",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "month", type: "string", example: "2026-01"),
                        new OA\Property(property: "period", type: "object"),
                        new OA\Property(property: "summary", type: "object", properties: [
                            new OA\Property(property: "income", type: "integer"),
                            new OA\Property(property: "expense", type: "integer"),
                            new OA\Property(property: "balance", type: "integer"),
                            new OA\Property(property: "transaction_count", type: "integer"),
                        ]),
                        new OA\Property(property: "income_by_category", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "expense_by_category", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "daily", type: "object", description: "Transactions par jour"),
                        new OA\Property(property: "by_account", type: "array", items: new OA\Items(type: "object")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function monthly(Request $request): JsonResponse
    {
        $user = $request->user();
        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // Revenus par catégorie
        $incomeByCategory = $user->transactions()
            ->with('category')
            ->where('type', 'income')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn($item) => [
                'category_id' => $item->category_id,
                'name' => $item->category?->name ?? 'Sans catégorie',
                'color' => $item->category?->color ?? '#808080',
                'total' => $item->total,
            ]);

        // Dépenses par catégorie avec budget
        $expenseByCategory = $user->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn($item) => [
                'category_id' => $item->category_id,
                'name' => $item->category?->name ?? 'Sans catégorie',
                'color' => $item->category?->color ?? '#808080',
                'total' => $item->total,
                'budget_limit' => $item->category?->budget_limit,
                'budget_percentage' => $item->category?->budget_limit
                    ? round(($item->total / $item->category->budget_limit) * 100, 1)
                    : null,
            ]);

        // Transactions par jour
        $dailyTransactions = $user->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(date) as day, type, SUM(amount) as total')
            ->groupBy('day', 'type')
            ->get()
            ->groupBy('day')
            ->map(fn($items) => [
                'income' => $items->where('type', 'income')->sum('total'),
                'expense' => $items->where('type', 'expense')->sum('total'),
            ]);

        // Par compte
        $byAccount = $user->accounts()
            ->with(['transactions' => function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
            }])
            ->get()
            ->map(fn($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'color' => $account->color,
                'income' => $account->transactions->where('type', 'income')->sum('amount'),
                'expense' => $account->transactions->where('type', 'expense')->sum('amount'),
            ]);

        $totalIncome = $incomeByCategory->sum('total');
        $totalExpense = $expenseByCategory->sum('total');

        return response()->json([
            'month' => $month,
            'period' => [
                'start' => $startOfMonth->toDateString(),
                'end' => $endOfMonth->toDateString(),
            ],
            'summary' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'balance' => $totalIncome - $totalExpense,
                'transaction_count' => $user->transactions()
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->count(),
            ],
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
            'daily' => $dailyTransactions,
            'by_account' => $byAccount,
        ]);
    }

    #[OA\Get(
        path: "/stats/trends",
        summary: "Tendances sur plusieurs mois",
        tags: ["Stats"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "months", in: "query", schema: new OA\Schema(type: "integer", minimum: 1, maximum: 12, default: 6)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tendances financières",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "period_months", type: "integer"),
                        new OA\Property(property: "trends", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "month", type: "string", example: "2026-01"),
                                new OA\Property(property: "label", type: "string", example: "Jan 2026"),
                                new OA\Property(property: "income", type: "integer"),
                                new OA\Property(property: "expense", type: "integer"),
                                new OA\Property(property: "balance", type: "integer"),
                                new OA\Property(property: "savings_rate", type: "number"),
                            ]
                        )),
                        new OA\Property(property: "averages", type: "object", properties: [
                            new OA\Property(property: "income", type: "integer"),
                            new OA\Property(property: "expense", type: "integer"),
                            new OA\Property(property: "savings_rate", type: "number"),
                        ]),
                        new OA\Property(property: "last_month_vs_average", type: "object", properties: [
                            new OA\Property(property: "income_trend", type: "number"),
                            new OA\Property(property: "expense_trend", type: "number"),
                            new OA\Property(property: "income_direction", type: "string", enum: ["up", "down"]),
                            new OA\Property(property: "expense_direction", type: "string", enum: ["up", "down"]),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function trends(Request $request): JsonResponse
    {
        $user = $request->user();
        $months = $request->input('months', 6);
        $months = min(max($months, 1), 12);

        $trends = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $income = $user->transactions()
                ->where('type', 'income')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $expense = $user->transactions()
                ->where('type', 'expense')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $trends->push([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
                'savings_rate' => $income > 0
                    ? round((($income - $expense) / $income) * 100, 1)
                    : 0,
            ]);
        }

        // Calculs de moyenne et tendance
        $avgIncome = $trends->avg('income');
        $avgExpense = $trends->avg('expense');
        $avgSavingsRate = $trends->avg('savings_rate');

        // Tendance (comparaison dernier mois vs moyenne)
        $lastMonth = $trends->last();
        $incomeTrend = $avgIncome > 0
            ? round((($lastMonth['income'] - $avgIncome) / $avgIncome) * 100, 1)
            : 0;
        $expenseTrend = $avgExpense > 0
            ? round((($lastMonth['expense'] - $avgExpense) / $avgExpense) * 100, 1)
            : 0;

        return response()->json([
            'period_months' => $months,
            'trends' => $trends,
            'averages' => [
                'income' => round($avgIncome),
                'expense' => round($avgExpense),
                'savings_rate' => round($avgSavingsRate, 1),
            ],
            'last_month_vs_average' => [
                'income_trend' => $incomeTrend,
                'expense_trend' => $expenseTrend,
                'income_direction' => $incomeTrend >= 0 ? 'up' : 'down',
                'expense_direction' => $expenseTrend >= 0 ? 'up' : 'down',
            ],
        ]);
    }
}
