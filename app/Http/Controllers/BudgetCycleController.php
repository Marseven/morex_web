<?php

namespace App\Http\Controllers;

use App\Models\BudgetClosure;
use App\Models\BudgetCycle;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetCycleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Cycle actif
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        // Historique des clôtures
        $closures = BudgetClosure::where('user_id', $user->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($closure) {
                return [
                    'id' => $closure->id,
                    'year' => $closure->year,
                    'month' => $closure->month,
                    'period_name' => Carbon::create($closure->year, $closure->month, 1)->locale('fr')->isoFormat('MMMM YYYY'),
                    'total_income' => $closure->total_income,
                    'total_spent' => $closure->total_spent,
                    'total_saved' => $closure->total_saved,
                    'created_at' => $closure->created_at,
                ];
            });

        // Catégories de dépense (perso + système) avec leur budget actuel,
        // pour permettre l'ajustement au lancement d'un cycle.
        $categories = Category::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->where('type', 'expense')
            ->orderBy('order_index')
            ->get(['id', 'name', 'budget_limit', 'color', 'icon']);

        // Revenu suggéré = moyenne des revenus des cycles clôturés (pour pré-remplir).
        $suggestedIncome = (int) round(BudgetClosure::where('user_id', $user->id)->avg('total_income') ?? 0);

        return Inertia::render('BudgetCycles/Index', [
            'activeCycle' => $activeCycle,
            'closures' => $closures,
            'categories' => $categories,
            'suggestedIncome' => $suggestedIncome,
            // Cible d'épargne (objectif financier) affichée comme repère.
            'savingsTargetRate' => 25,
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'expected_income' => ['nullable', 'integer', 'min:0'],
            'budgets' => ['sometimes', 'array'],
            'budgets.*.id' => ['required', 'uuid'],
            'budgets.*.budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        // Appliquer les budgets ajustés par catégorie (dépenses uniquement, perso ou système)
        if (!empty($validated['budgets'])) {
            foreach ($validated['budgets'] as $b) {
                Category::whereKey($b['id'])
                    ->where('type', 'expense')
                    ->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)->orWhereNull('user_id');
                    })
                    ->update(['budget_limit' => $b['budget_limit'] ?? null]);
            }
        }

        // Clôturer le cycle actif s'il existe
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now();

        if ($activeCycle) {
            $activeCycle->close($startDate->copy()->subDay());
            $activeCycle->createClosure();
        }

        // Créer le nouveau cycle
        $periodName = BudgetCycle::generatePeriodName($startDate);

        $totalBudget = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereNull('user_id');
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        $newCycle = new BudgetCycle([
            'start_date' => $startDate,
            'period_name' => $periodName,
            'total_budget' => $totalBudget,
            'total_spent' => 0,
            'expected_income' => $validated['expected_income'] ?? 0,
            'status' => 'active',
        ]);
        $newCycle->user_id = $user->id;
        $newCycle->save();

        return redirect()->route('budget-cycles.index')
            ->with('success', "Nouveau cycle '{$periodName}' démarré avec succès.");
    }

    /**
     * Ajuste la date de début du cycle actif (corrige un cycle démarré à la mauvaise date,
     * qui ferait fuir les transactions du mois précédent dans « dépensé ce mois »).
     */
    public function updateActive(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
        ]);

        $cycle = BudgetCycle::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$cycle) {
            return back()->with('error', 'Aucun cycle actif.');
        }

        $cycle->start_date = Carbon::parse($validated['start_date']);
        $cycle->period_name = BudgetCycle::generatePeriodName($cycle->start_date);
        $cycle->save();
        $cycle->updateTotals(); // recalcule total_spent/total_budget sur la nouvelle plage

        return back()->with('success', 'Date de début du cycle mise à jour.');
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            'end_date' => ['sometimes', 'date'],
        ]);

        $cycle = BudgetCycle::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$cycle) {
            return redirect()->route('budget-cycles.index')
                ->with('error', 'Aucun cycle actif à clôturer.');
        }

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now();

        $cycle->close($endDate);
        $cycle->createClosure();

        return redirect()->route('budget-cycles.index')
            ->with('success', "Cycle '{$cycle->period_name}' clôturé avec succès.");
    }
}
