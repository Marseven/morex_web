<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PreviewNotesRequest;
use App\Models\Category;
use App\Services\BatchEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Écran web de saisie en lot ; l'enregistrement passe par l'import de transactions. */
final class BatchEntryController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Transactions/BatchEntry', [
            'accounts' => $user->accounts()->orderByDesc('is_default')->orderBy('order_index')->get(['id', 'name', 'is_default']),
            'categories' => Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('is_system', true))
                ->whereNotIn('id', Category::RECONCILIATION_IDS)
                ->orderBy('order_index')
                ->get(['id', 'name', 'type']),
            'today' => now()->toDateString(),
        ]);
    }

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
