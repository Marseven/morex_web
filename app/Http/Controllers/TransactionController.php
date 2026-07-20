<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Rules\OwnedOrSystemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
     * Applique les filtres de la liste (partagés entre l'affichage et l'export).
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('beneficiary', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $query = $request->user()
            ->transactions()
            ->with(['category', 'account', 'transferToAccount']);

        $this->applyFilters($query, $request);

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
            'filters' => $request->only(['type', 'account_id', 'category_id', 'start_date', 'end_date', 'search']),
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
        $userId = $request->user()->id;
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory($userId)],
            'account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'transfer_to_account_id' => ['nullable', 'uuid', "exists:accounts,id,user_id,{$userId}", 'different:account_id'],
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

        $transaction->load(['category', 'account', 'transferToAccount']);

        return Inertia::render('Transactions/Edit', [
            // On sérialise la date en Y-m-d (fuseau de l'app) pour <input type="date"> :
            // le cast 'date' brut renverrait un datetime ISO en UTC → décalage d'un jour.
            'transaction' => array_merge($transaction->toArray(), [
                'date' => $transaction->date?->format('Y-m-d'),
            ]),
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $userId = $request->user()->id;
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory($userId)],
            'account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'transfer_to_account_id' => ['nullable', 'uuid', "exists:accounts,id,user_id,{$userId}"],
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

    /**
     * Export CSV des transactions (filtres de la liste appliqués).
     * Même ordre de colonnes que l'import → aller-retour possible,
     * et format directement exploitable par une IA pour analyse.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $request->user()->transactions()->with(['category', 'account']);
        $this->applyFilters($query, $request);

        $filename = 'transactions-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
            fputcsv($out, ['date', 'montant', 'type', 'beneficiaire', 'description', 'categorie', 'compte'], ';');

            $query->orderBy('date')->chunk(500, function ($transactions) use ($out) {
                foreach ($transactions as $t) {
                    fputcsv($out, [
                        $t->date?->format('Y-m-d'),
                        $t->amount,
                        $t->type,
                        $t->beneficiary,
                        $t->description,
                        $t->category?->name,
                        $t->account?->name,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Page d'import de relevé (CSV).
     */
    public function showImport(Request $request): Response
    {
        $accounts = $request->user()->accounts()->orderBy('order_index')->get();
        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)->orWhere('is_system', true);
        })->orderBy('order_index')->get();

        return Inertia::render('Transactions/Import', [
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    /**
     * Crée en masse les transactions d'un relevé importé.
     */
    public function import(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'account_id' => ['required', 'uuid', "exists:accounts,id,user_id,{$userId}"],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.amount' => ['required', 'integer', 'min:1'],
            'rows.*.type' => ['required', 'in:expense,income'],
            'rows.*.beneficiary' => ['nullable', 'string', 'max:255'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
            'rows.*.category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory($userId)],
        ]);

        DB::transaction(function () use ($validated, $userId) {
            foreach ($validated['rows'] as $row) {
                $tx = new Transaction([
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'account_id' => $validated['account_id'],
                    'category_id' => $row['category_id'] ?? null,
                    'beneficiary' => $row['beneficiary'] ?? null,
                    'description' => $row['description'] ?? null,
                    'date' => $row['date'],
                ]);
                $tx->user_id = $userId;
                $tx->save();
            }
        });

        return redirect()->route('transactions.index')
            ->with('success', count($validated['rows']) . ' transaction(s) importée(s).');
    }

    /**
     * Liste des transactions en attente de validation (importées par SMS).
     */
    public function pending(Request $request): Response
    {
        $transactions = $request->user()->transactions()
            ->with(['category', 'account'])
            ->where('status', 'pending_validation')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        $categories = Category::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)->orWhere('is_system', true);
        })->orderBy('order_index')->get();

        return Inertia::render('Transactions/Pending', [
            'transactions' => $transactions,
            'categories' => $categories,
        ]);
    }

    /**
     * Valide une transaction en attente (optionnellement en (re)catégorisant).
     */
    public function validateTransaction(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid', new OwnedOrSystemCategory($request->user()->id)],
        ]);

        if (array_key_exists('category_id', $validated)) {
            $transaction->category_id = $validated['category_id'];
        }
        $transaction->status = 'confirmed';
        $transaction->save();

        return back()->with('success', 'Transaction validée.');
    }
}
