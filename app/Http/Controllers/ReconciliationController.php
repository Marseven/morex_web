<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use App\Http\Resources\UnclearedTransactionResource;
use App\Models\Account;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Écran web de rapprochement (design A : page unique en deux colonnes). */
final class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $reconciliations) {}

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('update', $account);
        $last = $this->reconciliations->lastReconciliation($account);

        return Inertia::render('Accounts/Reconcile', [
            'account' => $account->only(['id', 'name', 'balance', 'color']),
            'lastReconciliation' => $last ? (new ReconciliationResource($last))->resolve($request) : null,
            'olderCount' => $this->reconciliations->olderThanWindowCount($account),
            'uncleared' => UnclearedTransactionResource::collection(
                $this->reconciliations->uncleared($account)->load('category')
            )->resolve($request),
        ]);
    }

    public function store(StoreReconciliationRequest $request, Account $account): RedirectResponse
    {
        $reconciliation = $this->reconciliations->reconcile(
            $account,
            $request->statementBalance(),
            $request->clearedIds(),
            $request->note(),
        );

        $message = $reconciliation->difference === 0
            ? "« {$account->name} » est rapproché, sans écart."
            : sprintf('« %s » est rapproché : écart de %+d FCFA enregistré.', $account->name, $reconciliation->difference);

        return redirect()->route('accounts.index')->with('success', $message);
    }
}
