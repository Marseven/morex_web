<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BudgetClosure;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Récupérer le cycle budgétaire actif
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        // Dates de la période budgétaire
        $startDateCarbon = $activeCycle?->start_date ?? now()->startOfMonth();
        $endDateCarbon = $activeCycle?->end_date;

        // Format string pour les requêtes SQL (évite les problèmes de timezone)
        $startDateStr = $startDateCarbon->format('Y-m-d');
        $endDateStr = $endDateCarbon?->format('Y-m-d');

        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->withSum(['transactions as spent_this_month' => function ($q) use ($user, $startDateStr, $endDateStr) {
            $q->where('user_id', $user->id)
              ->where('type', 'expense')
              ->where('date', '>=', $startDateStr);
            if ($endDateStr) {
                $q->where('date', '<=', $endDateStr);
            }
        }], 'amount')
        ->orderBy('type')
        ->orderBy('order_index')
        ->get();

        // Vérifier si la période en cours a déjà été clôturée
        $currentMonthClosed = $activeCycle === null;

        // Historique des clôtures
        $closures = BudgetClosure::where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        // Nom de la période actuelle
        $currentPeriodName = $activeCycle?->period_name ?? 'Aucune période active';

        return Inertia::render('Budgets/Index', [
            'categories' => $categories,
            'currentMonthClosed' => $currentMonthClosed,
            'closures' => $closures,
            'currentMonth' => [
                'year' => $startDateCarbon->year,
                'month' => $startDateCarbon->month,
                'name' => $currentPeriodName,
                'start_date' => $startDateCarbon->format('d/m/Y'),
                'end_date' => $endDateCarbon?->format('d/m/Y'),
            ],
            'activeCycle' => $activeCycle,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Budgets/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $request->user()->categories()->max('order_index') ?? -1;

        $request->user()->categories()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie créée avec succès.');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('Budgets/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update($validated);

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie mise à jour avec succès.');
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return redirect()->route('budgets.index')
            ->with('success', 'Catégorie supprimée avec succès.');
    }

    /**
     * Clôture le budget du mois en cours
     */
    public function closeBudget(Request $request)
    {
        $user = $request->user();
        $year = now()->year;
        $month = now()->month;

        // Vérifier si déjà clôturé
        $existingClosure = BudgetClosure::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existingClosure) {
            return back()->with('error', 'Ce mois a déjà été clôturé.');
        }

        // Récupérer les catégories avec budget et leurs dépenses
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->where('budget_limit', '>', 0)
        ->withSum(['transactions as spent' => function ($q) use ($year, $month) {
            $q->where('type', 'expense')
              ->whereMonth('date', $month)
              ->whereYear('date', $year);
        }], 'amount')
        ->get();

        if ($categories->isEmpty()) {
            return back()->with('error', 'Aucun budget défini à clôturer.');
        }

        // Calculer les totaux
        $totalBudget = $categories->sum('budget_limit');
        $totalSpent = $categories->sum('spent') ?? 0;
        $totalSaved = max(0, $totalBudget - $totalSpent);

        // Détails par catégorie
        $details = $categories->map(function ($cat) {
            return [
                'category_id' => $cat->id,
                'category_name' => $cat->name,
                'budget' => $cat->budget_limit,
                'spent' => $cat->spent ?? 0,
                'saved' => max(0, $cat->budget_limit - ($cat->spent ?? 0)),
            ];
        })->toArray();

        $transactionId = null;

        // Si il y a des économies, créer la transaction
        if ($totalSaved > 0) {
            // Récupérer ou créer le compte "Budget économisé"
            $savingsAccount = Account::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => 'Budget économisé',
                ],
                [
                    'type' => 'savings',
                    'initial_balance' => 0,
                    'balance' => 0,
                    'color' => '#22C55E',
                    'icon' => 'piggy-bank',
                    'is_default' => false,
                    'order_index' => 999,
                ]
            );

            // Récupérer ou créer la catégorie "Épargne budget"
            $savingsCategory = Category::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => 'Épargne budget',
                    'type' => 'income',
                ],
                [
                    'icon' => 'savings',
                    'color' => '#22C55E',
                    'is_system' => false,
                    'order_index' => 999,
                ]
            );

            // Nom du mois pour la description
            $months = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ];
            $monthName = $months[$month];

            // Créer la transaction d'économie
            $transaction = Transaction::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'account_id' => $savingsAccount->id,
                'category_id' => $savingsCategory->id,
                'type' => 'income',
                'amount' => $totalSaved,
                'beneficiary' => 'Clôture budget',
                'description' => "Économies du mois de {$monthName} {$year}",
                'date' => now(),
            ]);

            // Mettre à jour le solde du compte
            $savingsAccount->increment('balance', $totalSaved);

            $transactionId = $transaction->id;
        }

        // Créer l'enregistrement de clôture
        BudgetClosure::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'total_saved' => $totalSaved,
            'transaction_id' => $transactionId,
            'details' => $details,
        ]);

        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $message = $totalSaved > 0
            ? "Mois de {$months[$month]} clôturé. " . number_format($totalSaved, 0, ',', ' ') . " FCFA transférés vers Budget économisé."
            : "Mois de {$months[$month]} clôturé. Aucune économie ce mois-ci.";

        return back()->with('success', $message);
    }
}
