<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request): Response
    {
        $goals = $request->user()
            ->goals()
            ->with('account')
            ->orderByRaw("FIELD(status, 'active', 'completed', 'cancelled')")
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_target' => $goals->where('status', 'active')->sum('target_amount'),
            'total_current' => $goals->where('status', 'active')->sum('current_amount'),
            'active_count' => $goals->where('status', 'active')->count(),
            'completed_count' => $goals->where('status', 'completed')->count(),
        ];

        return Inertia::render('Goals/Index', [
            'goals' => $goals,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = $request->user()->accounts()->orderBy('order_index')->get();

        return Inertia::render('Goals/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:savings,debt,investment,custom'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->goals()->create([
            ...$validated,
            'current_amount' => $validated['current_amount'] ?? 0,
            'status' => 'active',
        ]);

        return redirect()->route('goals.index')
            ->with('success', 'Objectif créé avec succès.');
    }

    public function edit(Request $request, Goal $goal): Response
    {
        $this->authorize('update', $goal);

        $accounts = $request->user()->accounts()->orderBy('order_index')->get();

        return Inertia::render('Goals/Edit', [
            'goal' => $goal->load('account'),
            'accounts' => $accounts,
        ]);
    }

    public function update(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:savings,debt,investment,custom'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['required', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'status' => ['required', 'in:active,completed,cancelled'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $goal->update($validated);

        return redirect()->route('goals.index')
            ->with('success', 'Objectif mis à jour avec succès.');
    }

    public function destroy(Goal $goal)
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return redirect()->route('goals.index')
            ->with('success', 'Objectif supprimé avec succès.');
    }

    public function contribute(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $goal->addAmount($validated['amount']);

        return back()->with('success', 'Contribution ajoutée avec succès.');
    }
}
