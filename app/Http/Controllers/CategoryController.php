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
        ->get()
        ->map(function ($category) {
            // S'assurer que spent_this_month est un entier (évite la concaténation de strings en JS)
            $category->spent_this_month = (int) ($category->spent_this_month ?? 0);
            return $category;
        });

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

        // Récupérer le cycle budgétaire actif
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$activeCycle) {
            return back()->with('error', 'Aucune période budgétaire active.');
        }

        $startDate = $activeCycle->start_date->format('Y-m-d');
        $periodName = $activeCycle->period_name;

        // Extraire mois/année pour la clôture
        $year = $activeCycle->start_date->year;
        $month = $activeCycle->start_date->month;
        // Si le cycle commence en fin de mois précédent, utiliser le mois suivant
        if ($activeCycle->start_date->day >= 25) {
            $month = $activeCycle->start_date->addMonth()->month;
            $year = $activeCycle->start_date->addMonth()->year;
        }

        // Vérifier si déjà clôturé
        $existingClosure = BudgetClosure::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existingClosure) {
            return back()->with('error', 'Cette période a déjà été clôturée.');
        }

        // Calculer les revenus de la période
        $totalIncome = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->where('date', '>=', $startDate)
            ->sum('amount');

        // Calculer les dépenses de la période
        $totalExpenses = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('date', '>=', $startDate)
            ->sum('amount');

        // Solde net = revenus - dépenses (peut être négatif)
        $netBalance = $totalIncome - $totalExpenses;

        // Détails par catégorie de dépenses
        $expenseCategories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->where('type', 'expense')
        ->get();

        $details = [];
        foreach ($expenseCategories as $cat) {
            $spent = Transaction::where('user_id', $user->id)
                ->where('category_id', $cat->id)
                ->where('type', 'expense')
                ->where('date', '>=', $startDate)
                ->sum('amount');

            if ($spent > 0) {
                $details[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'budget' => $cat->budget_limit ?? 0,
                    'spent' => $spent,
                ];
            }
        }

        // Clôturer le cycle actif
        $activeCycle->update([
            'end_date' => now(),
            'status' => 'closed',
            'total_spent' => $totalExpenses,
        ]);

        // Créer l'enregistrement de clôture
        BudgetClosure::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
            'total_budget' => $totalIncome,
            'total_spent' => $totalExpenses,
            'total_saved' => $netBalance,
            'details' => $details,
        ]);

        $status = $netBalance >= 0 ? 'Excédent' : 'Déficit';
        $message = "{$periodName} clôturé. {$status}: " . number_format(abs($netBalance), 0, ',', ' ') . " FCFA";

        return back()->with('success', $message);
    }
}
