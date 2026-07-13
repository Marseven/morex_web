<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()->transfers()
            ->with(['fromAccount', 'toAccount']);

        if ($request->filled('account_id')) {
            $accountId = $request->account_id;
            $query->where(function ($q) use ($accountId) {
                $q->where('from_account_id', $accountId)
                  ->orWhere('to_account_id', $accountId);
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $transfers = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $accounts = $request->user()->accounts()->orderBy('order_index')->get();

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'accounts' => $accounts,
            'filters' => $request->only(['account_id', 'start_date', 'end_date']),
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = $request->user()->accounts()->orderBy('order_index')->get();

        return Inertia::render('Transfers/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'from_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'to_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}", 'different:from_account_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
        ]);

        $transfer = new Transfer($validated);
        $transfer->user_id = $userId;
        $transfer->save();

        return redirect()->route('transfers.index')
            ->with('success', 'Transfert créé avec succès.');
    }

    public function edit(Request $request, Transfer $transfer): Response
    {
        $this->authorize('update', $transfer);

        $accounts = $request->user()->accounts()->orderBy('order_index')->get();

        $transfer->load(['fromAccount', 'toAccount']);

        return Inertia::render('Transfers/Edit', [
            // Date en Y-m-d (fuseau app) pour <input type="date"> — évite le décalage UTC.
            'transfer' => array_merge($transfer->toArray(), [
                'date' => $transfer->date?->format('Y-m-d'),
            ]),
            'accounts' => $accounts,
        ]);
    }

    public function update(Request $request, Transfer $transfer)
    {
        $this->authorize('update', $transfer);

        $userId = $request->user()->id;

        $validated = $request->validate([
            'from_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'to_account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}", 'different:from_account_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
        ]);

        $transfer->update($validated);

        return redirect()->route('transfers.index')
            ->with('success', 'Transfert modifié avec succès.');
    }

    public function destroy(Request $request, Transfer $transfer)
    {
        $this->authorize('delete', $transfer);

        $transfer->delete();

        return redirect()->route('transfers.index')
            ->with('success', 'Transfert supprimé.');
    }
}
