<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use App\Http\Resources\UnclearedTransactionResource;
use App\Models\Account;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $reconciliations) {}

    #[OA\Get(
        path: '/accounts/{account}/reconciliation',
        summary: 'Aperçu du rapprochement : solde, dernier rapprochement, opérations à pointer',
        tags: ['Reconciliation'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Aperçu'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: "Compte d'un autre utilisateur"),
        ]
    )]
    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);
        $last = $this->reconciliations->lastReconciliation($account);

        return response()->json(['data' => [
            'account_id' => $account->id,
            'balance' => $account->balance,
            'last_reconciliation' => $last ? new ReconciliationResource($last) : null,
            'older_count' => $this->reconciliations->olderThanWindowCount($account),
            'uncleared' => UnclearedTransactionResource::collection(
                $this->reconciliations->uncleared($account)->load('category')
            ),
        ]]);
    }

    #[OA\Post(
        path: '/accounts/{account}/reconciliations',
        summary: "Valider un rapprochement (l'écart devient une opération tracée)",
        tags: ['Reconciliation'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Rapprochement enregistré'),
            new OA\Response(response: 403, description: "Compte d'un autre utilisateur"),
            new OA\Response(response: 422, description: 'Validation ou INVALID_RECONCILIATION'),
        ]
    )]
    public function store(StoreReconciliationRequest $request, Account $account): JsonResponse
    {
        $reconciliation = $this->reconciliations->reconcile(
            $account,
            $request->statementBalance(),
            $request->clearedIds(),
            $request->note(),
        );

        return (new ReconciliationResource($reconciliation->load('account')))
            ->response()
            ->setStatusCode(201);
    }
}
