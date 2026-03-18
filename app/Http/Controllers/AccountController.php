<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = $request->user()
            ->accounts()
            ->withSum(['transactions as income_total' => fn($q) => $q->where('type', 'income')], 'amount')
            ->withSum(['transactions as expense_total' => fn($q) => $q->where('type', 'expense')], 'amount')
            ->withSum(['transactions as transfers_out_total' => fn($q) => $q->where('type', 'transfer')], 'amount')
            ->withSum(['incomingTransfers as transfers_in_total'], 'amount')
            ->orderBy('order_index')
            ->get()
            ->map(function ($account) {
                $calculated = $account->initial_balance
                    + ($account->income_total ?? 0)
                    - ($account->expense_total ?? 0)
                    - ($account->transfers_out_total ?? 0)
                    + ($account->transfers_in_total ?? 0);
                $account->calculated_balance = $calculated;
                $account->ecart = $account->balance - $calculated;
                return $account;
            });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'totalBalance' => $accounts->sum('balance'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:current,checking,savings,cash,credit,investment'],
            'initial_balance' => ['required', 'integer'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ]);

        $maxOrder = $request->user()->accounts()->max('order_index') ?? -1;

        $account = $request->user()->accounts()->create([
            ...$validated,
            'balance' => $validated['initial_balance'],
            'order_index' => $maxOrder + 1,
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('accounts.index')
            ->with('success', 'Compte créé avec succès.');
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('Accounts/Edit', [
            'account' => $account,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:current,checking,savings,cash,credit,investment'],
            'initial_balance' => ['required', 'integer'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ]);

        $account->update($validated);

        if ($validated['is_default'] ?? false) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('accounts.index')
            ->with('success', 'Compte mis à jour avec succès.');
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Compte supprimé avec succès.');
    }

    public function adjustBalance(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'balance' => ['required', 'integer'],
        ]);

        $account->adjustToBalance($validated['balance']);

        return redirect()->route('accounts.index')
            ->with('success', "Solde de \"{$account->name}\" ajusté avec succès.");
    }
}
