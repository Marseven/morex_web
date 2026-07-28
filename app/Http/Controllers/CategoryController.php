<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BudgetClosure;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

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

        $periodName = $activeCycle->period_name;

        // Un SEUL chemin de clôture : on borne bien la période (start_date → aujourd'hui),
        // et createClosure() calcule proprement revenus, dépenses, budget et épargne
        // (total_saved = revenus − dépenses). Évite le bug d'addition sans borne de fin
        // et l'écriture du revenu dans total_budget.
        $activeCycle->close(now());
        $closure = $activeCycle->createClosure();

        $status = $closure->total_saved >= 0 ? 'Excédent' : 'Déficit';
        $message = "{$periodName} clôturé. {$status}: "
            . number_format(abs($closure->total_saved), 0, ',', ' ') . " FCFA";

        return back()->with('success', $message);
    }

    /**
     * Met à jour en masse les budgets (budget_limit) des catégories de dépense.
     * Utile pour appliquer une proposition (ex. issue d'une analyse IA) sans relancer un cycle.
     */
    public function importBudgets(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'budgets' => ['required', 'array', 'min:1', 'max:200'],
            'budgets.*.id' => ['required', 'uuid'],
            'budgets.*.budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $applied = 0;
        foreach ($validated['budgets'] as $b) {
            $applied += Category::whereKey($b['id'])
                ->where('type', 'expense')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhereNull('user_id');
                })
                ->update(['budget_limit' => $b['budget_limit'] ?? null]);
        }

        // Aligner le total du cycle actif sur les nouveaux budgets.
        BudgetCycle::where('user_id', $userId)->where('status', 'active')->first()?->updateTotals();

        return back()->with('success', "{$applied} budget(s) mis à jour.");
    }
}
