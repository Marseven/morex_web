<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewNotesRequest;
use App\Services\BatchEntryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class BatchEntryController extends Controller
{
    #[OA\Post(
        path: '/transactions/notes/preview',
        summary: 'Analyser des notes de dépenses (saisie en lot) sans rien enregistrer',
        tags: ['Transactions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lignes analysées avec catégorie et compte suggérés'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Validation (NOTES_TOO_LONG, compte inconnu)'),
        ]
    )]
    public function preview(PreviewNotesRequest $request, BatchEntryService $batch): JsonResponse
    {
        return response()->json(['data' => $batch->preview(
            $request->user(),
            $request->notes(),
            $request->referenceDate(),
            $request->defaultAccount(),
        )]);
    }
}
