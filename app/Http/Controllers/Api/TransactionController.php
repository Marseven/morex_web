<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{
    #[OA\Get(
        path: "/transactions",
        summary: "Liste des transactions",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["expense", "income", "transfer"])),
            new OA\Parameter(name: "account_id", in: "query", schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "category_id", in: "query", schema: new OA\Schema(type: "string", format: "uuid")),
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 20)),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer", default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des transactions",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Transaction")),
                        new OA\Property(property: "meta", ref: "#/components/schemas/PaginationMeta"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->transactions()->with(['category', 'account', 'transferToAccount']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $transactions = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return TransactionResource::collection($transactions);
    }

    #[OA\Post(
        path: "/transactions",
        summary: "Créer une transaction",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "type", "account_id", "date"],
                properties: [
                    new OA\Property(property: "amount", type: "integer", example: 25000, description: "Montant en FCFA"),
                    new OA\Property(property: "type", type: "string", enum: ["expense", "income", "transfer"]),
                    new OA\Property(property: "category_id", type: "string", format: "uuid", nullable: true),
                    new OA\Property(property: "account_id", type: "string", format: "uuid"),
                    new OA\Property(property: "beneficiary", type: "string", nullable: true, example: "Supermarché"),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "date", type: "string", format: "date", example: "2026-01-27"),
                    new OA\Property(property: "transfer_to_account_id", type: "string", format: "uuid", nullable: true, description: "Requis pour les transferts"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Transaction créée", content: new OA\JsonContent(ref: "#/components/schemas/Transaction")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
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
            return response()->json([
                'message' => 'Le compte de destination est requis pour un transfert.',
                'errors' => ['transfer_to_account_id' => ['Ce champ est requis pour un transfert.']],
            ], 422);
        }

        $transaction = $request->user()->transactions()->create($validated);

        return response()->json(
            new TransactionResource($transaction->load(['category', 'account', 'transferToAccount'])),
            201
        );
    }

    #[OA\Get(
        path: "/transactions/{id}",
        summary: "Détail d'une transaction",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail de la transaction", content: new OA\JsonContent(ref: "#/components/schemas/Transaction")),
            new OA\Response(response: 404, description: "Transaction non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        return new TransactionResource($transaction->load(['category', 'account', 'transferToAccount']));
    }

    #[OA\Put(
        path: "/transactions/{id}",
        summary: "Modifier une transaction",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "amount", type: "integer"),
                    new OA\Property(property: "type", type: "string", enum: ["expense", "income", "transfer"]),
                    new OA\Property(property: "category_id", type: "string", format: "uuid", nullable: true),
                    new OA\Property(property: "account_id", type: "string", format: "uuid"),
                    new OA\Property(property: "beneficiary", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "date", type: "string", format: "date"),
                    new OA\Property(property: "transfer_to_account_id", type: "string", format: "uuid", nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Transaction modifiée", content: new OA\JsonContent(ref: "#/components/schemas/Transaction")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Transaction non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'amount' => ['sometimes', 'integer', 'min:1'],
            'type' => ['sometimes', 'in:expense,income,transfer'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'account_id' => ['sometimes', 'uuid', 'exists:accounts,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['sometimes', 'date'],
            'transfer_to_account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
        ]);

        $transaction->update($validated);

        return new TransactionResource($transaction->fresh()->load(['category', 'account', 'transferToAccount']));
    }

    #[OA\Delete(
        path: "/transactions/{id}",
        summary: "Supprimer une transaction",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Transaction supprimée"),
            new OA\Response(response: 404, description: "Transaction non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: "/transactions-stats",
        summary: "Statistiques des transactions",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques des transactions",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "period", type: "object", properties: [
                            new OA\Property(property: "start", type: "string", format: "date"),
                            new OA\Property(property: "end", type: "string", format: "date"),
                        ]),
                        new OA\Property(property: "income", type: "integer"),
                        new OA\Property(property: "expense", type: "integer"),
                        new OA\Property(property: "balance", type: "integer"),
                        new OA\Property(property: "by_category", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "category", type: "string"),
                                new OA\Property(property: "color", type: "string"),
                                new OA\Property(property: "total", type: "integer"),
                            ]
                        )),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $transactions = $request->user()->transactions()
            ->whereBetween('date', [$startDate, $endDate]);

        $income = (clone $transactions)->where('type', 'income')->sum('amount');
        $expense = (clone $transactions)->where('type', 'expense')->sum('amount');

        $byCategory = $request->user()->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category?->name ?? 'Sans catégorie',
                'color' => $item->category?->color ?? '#808080',
                'total' => $item->total,
            ]);

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'by_category' => $byCategory,
        ]);
    }
}
