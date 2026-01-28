<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    public function index(Request $request): Response
    {
        $debts = $request->user()
            ->debts()
            ->orderByRaw("FIELD(status, 'active', 'paid', 'cancelled')")
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_debt' => $debts->where('type', 'debt')->where('status', 'active')->sum('current_amount'),
            'total_credit' => $debts->where('type', 'credit')->where('status', 'active')->sum('current_amount'),
            'active_debts' => $debts->where('type', 'debt')->where('status', 'active')->count(),
            'active_credits' => $debts->where('type', 'credit')->where('status', 'active')->count(),
            'overdue_count' => $debts->where('status', 'active')->filter(fn($d) => $d->is_overdue)->count(),
        ];

        return Inertia::render('Debts/Index', [
            'debts' => $debts,
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Debts/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:debt,credit'],
            'initial_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $request->user()->debts()->create([
            ...$validated,
            'current_amount' => $validated['current_amount'] ?? $validated['initial_amount'],
            'status' => 'active',
        ]);

        return redirect()->route('debts.index')
            ->with('success', ($validated['type'] === 'debt' ? 'Dette' : 'Créance') . ' créée avec succès.');
    }

    public function edit(Request $request, Debt $debt): Response
    {
        $this->authorize('update', $debt);

        return Inertia::render('Debts/Edit', [
            'debt' => $debt,
        ]);
    }

    public function update(Request $request, Debt $debt)
    {
        $this->authorize('update', $debt);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:debt,credit'],
            'initial_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['required', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:active,paid,cancelled'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $debt->update($validated);

        return redirect()->route('debts.index')
            ->with('success', 'Mise à jour effectuée.');
    }

    public function destroy(Debt $debt)
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return redirect()->route('debts.index')
            ->with('success', 'Suppression effectuée.');
    }

    public function payment(Request $request, Debt $debt)
    {
        $this->authorize('update', $debt);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $debt->addPayment($validated['amount']);

        return back()->with('success', 'Paiement enregistré.');
    }
}
