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

        return Inertia::render('BudgetCycles/Index', [
            'activeCycle' => $activeCycle,
            'closures' => $closures,
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
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
            'status' => 'active',
        ]);
        $newCycle->user_id = $user->id;
        $newCycle->save();

        return redirect()->route('budget-cycles.index')
            ->with('success', "Nouveau cycle '{$periodName}' démarré avec succès.");
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
