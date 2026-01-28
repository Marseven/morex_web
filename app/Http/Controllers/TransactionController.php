<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()
            ->transactions()
            ->with(['category', 'account', 'transferToAccount']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $transactions = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $accounts = $request->user()->accounts()->orderBy('order_index')->get();
        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })->orderBy('order_index')->get();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
            'filters' => $request->only(['type', 'account_id', 'category_id', 'start_date', 'end_date']),
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = $request->user()->accounts()->orderBy('order_index')->get();
        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })->orderBy('order_index')->get();

        return Inertia::render('Transactions/Create', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'transfer_to_account_id' => ['nullable', 'uuid', 'exists:accounts,id', 'different:account_id'],
        ]);

        if ($validated['type'] === 'transfer' && !isset($validated['transfer_to_account_id'])) {
            return back()->withErrors([
                'transfer_to_account_id' => 'Le compte de destination est requis pour un transfert.',
            ]);
        }

        $request->user()->transactions()->create($validated);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction créée avec succès.');
    }

    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        $accounts = $request->user()->accounts()->orderBy('order_index')->get();
        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('is_system', true);
        })->orderBy('order_index')->get();

        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction->load(['category', 'account', 'transferToAccount']),
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'transfer_to_account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
        ]);

        $transaction->update($validated);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction mise à jour avec succès.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction supprimée avec succès.');
    }
}
