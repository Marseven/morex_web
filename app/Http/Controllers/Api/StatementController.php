<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyStatementRequest;
use App\Http\Requests\PreviewStatementRequest;
use App\Models\Account;
use App\Services\StatementMatcher;
use App\Services\StatementParser;
use App\Services\StatementService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class StatementController extends Controller
{
    #[OA\Post(
        path: '/accounts/{account}/statement/preview',
        summary: 'Lire un relevé collé et proposer les correspondances (rien n\'est enregistré)',
        tags: ['Statement'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lignes, statuts, candidates, opérations absentes'),
            new OA\Response(response: 403, description: "Compte d'un autre utilisateur"),
            new OA\Response(response: 422, description: 'Relevé trop long'),
        ]
    )]
    public function preview(PreviewStatementRequest $request, Account $account, StatementParser $parser, StatementMatcher $matcher): JsonResponse
    {
        $entries = $parser->parse($request->text(), $account->name, $request->referenceDate());

        return response()->json(['data' => $matcher->match($account, $entries)]);
    }

    #[OA\Post(
        path: '/accounts/{account}/statement',
        summary: 'Valider un relevé : rapprocher, créer ou ignorer chaque ligne',
        tags: ['Statement'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Récapitulatif'),
            new OA\Response(response: 403, description: "Compte d'un autre utilisateur"),
            new OA\Response(response: 422, description: 'Validation ou ALREADY_MATCHED'),
        ]
    )]
    public function store(ApplyStatementRequest $request, Account $account, StatementService $statements): JsonResponse
    {
        return response()->json(['data' => $statements->apply($account, $request->lines())]);
    }
}
