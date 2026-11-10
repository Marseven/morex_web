<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetAlert;
use App\Services\BudgetAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class BudgetAlertController extends Controller
{
    #[OA\Get(
        path: '/budget-alerts',
        summary: 'Alertes de plafond de la période en cours (cycle actif, sinon mois civil)',
        tags: ['Budgets'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Alertes'), new OA\Response(response: 401, description: 'Non authentifié')]
    )]
    public function index(Request $request, BudgetAlertService $alerts): JsonResponse
    {
        [$from] = $alerts->period($request->user());

        $data = BudgetAlert::with('category:id,name')
            ->where('user_id', $request->user()->id)
            ->where('cycle_start', '>=', $from->toDateString())
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (BudgetAlert $a) => [
                'id' => $a->id,
                'category_id' => $a->category_id,
                'category' => $a->category?->name,
                'threshold' => $a->threshold,
                'spent' => $a->spent,
                'limit' => $a->limit,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $data]);
    }
}
