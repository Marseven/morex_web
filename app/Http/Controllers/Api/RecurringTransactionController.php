<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecurringTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\RecurringTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class RecurringTransactionController extends Controller
{
    #[OA\Get(
        path: "/recurring-transactions",
        summary: "Liste des transactions récurrentes",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "active", in: "query", schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["income", "expense"])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des transactions récurrentes",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/RecurringTransaction")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->recurringTransactions()->with(['account', 'category']);

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $recurringTransactions = $query->orderByDesc('created_at')->get();

        return RecurringTransactionResource::collection($recurringTransactions);
    }

    #[OA\Post(
        path: "/recurring-transactions",
        summary: "Créer une transaction récurrente",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["type", "amount", "account_id", "category_id", "frequency", "start_date", "next_due_date"],
                properties: [
                    new OA\Property(property: "type", type: "string", enum: ["income", "expense"]),
                    new OA\Property(property: "amount", type: "integer", example: 50000, description: "Montant en FCFA"),
                    new OA\Property(property: "account_id", type: "string", format: "uuid"),
                    new OA\Property(property: "category_id", type: "string", format: "uuid"),
                    new OA\Property(property: "beneficiary", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "frequency", type: "string", enum: ["daily", "weekly", "biweekly", "monthly", "quarterly", "yearly"]),
                    new OA\Property(property: "day_of_month", type: "integer", minimum: 1, maximum: 31, nullable: true),
                    new OA\Property(property: "start_date", type: "string", format: "date"),
                    new OA\Property(property: "end_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "next_due_date", type: "string", format: "date"),
                    new OA\Property(property: "remaining_occurrences", type: "integer", nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Transaction récurrente créée"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', 'in:daily,weekly,biweekly,monthly,quarterly,yearly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'next_due_date' => ['required', 'date'],
            'remaining_occurrences' => ['nullable', 'integer', 'min:1'],
        ]);

        $recurringTransaction = $request->user()->recurringTransactions()->create([
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json(
            new RecurringTransactionResource($recurringTransaction->load(['account', 'category'])),
            201
        );
    }

    #[OA\Get(
        path: "/recurring-transactions/{id}",
        summary: "Détail d'une transaction récurrente",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail de la transaction récurrente"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, RecurringTransaction $recurringTransaction): RecurringTransactionResource
    {
        $this->authorize('view', $recurringTransaction);

        return new RecurringTransactionResource($recurringTransaction->load(['account', 'category']));
    }

    #[OA\Put(
        path: "/recurring-transactions/{id}",
        summary: "Modifier une transaction récurrente",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "type", type: "string", enum: ["income", "expense"]),
                    new OA\Property(property: "amount", type: "integer"),
                    new OA\Property(property: "account_id", type: "string", format: "uuid"),
                    new OA\Property(property: "category_id", type: "string", format: "uuid"),
                    new OA\Property(property: "beneficiary", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "frequency", type: "string", enum: ["daily", "weekly", "biweekly", "monthly", "quarterly", "yearly"]),
                    new OA\Property(property: "day_of_month", type: "integer", nullable: true),
                    new OA\Property(property: "end_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "next_due_date", type: "string", format: "date"),
                    new OA\Property(property: "remaining_occurrences", type: "integer", nullable: true),
                    new OA\Property(property: "is_active", type: "boolean"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Transaction récurrente modifiée"),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, RecurringTransaction $recurringTransaction): RecurringTransactionResource
    {
        $this->authorize('update', $recurringTransaction);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:income,expense'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'account_id' => ['sometimes', 'uuid', 'exists:accounts,id'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['sometimes', 'in:daily,weekly,biweekly,monthly,quarterly,yearly'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_date' => ['nullable', 'date'],
            'next_due_date' => ['sometimes', 'date'],
            'remaining_occurrences' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recurringTransaction->update($validated);

        return new RecurringTransactionResource($recurringTransaction->fresh()->load(['account', 'category']));
    }

    #[OA\Delete(
        path: "/recurring-transactions/{id}",
        summary: "Supprimer une transaction récurrente",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Transaction récurrente supprimée"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorize('delete', $recurringTransaction);

        $recurringTransaction->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/recurring-transactions/{id}/generate",
        summary: "Générer manuellement une transaction depuis la récurrence",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Transaction générée"),
            new OA\Response(response: 400, description: "Transaction non due"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function generate(Request $request, RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorize('update', $recurringTransaction);

        if (!$recurringTransaction->is_active) {
            return response()->json([
                'message' => 'Cette transaction récurrente n\'est pas active',
            ], 400);
        }

        // Generate the transaction
        $transaction = $recurringTransaction->generateTransaction();

        // Update next due date
        $recurringTransaction->updateNextDueDate();

        return response()->json([
            'message' => 'Transaction générée avec succès',
            'transaction' => new TransactionResource($transaction->load(['account', 'category'])),
            'recurring_transaction' => new RecurringTransactionResource($recurringTransaction->fresh()->load(['account', 'category'])),
        ]);
    }

    #[OA\Post(
        path: "/recurring-transactions/process-due",
        summary: "Traiter toutes les transactions récurrentes dues",
        tags: ["Recurring Transactions"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Transactions traitées"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function processDue(Request $request): JsonResponse
    {
        $user = $request->user();
        $dueRecurrings = $user->recurringTransactions()->due()->get();

        $generated = [];
        foreach ($dueRecurrings as $recurring) {
            $transaction = $recurring->generateTransaction();
            $recurring->updateNextDueDate();
            $generated[] = [
                'recurring_id' => $recurring->id,
                'transaction_id' => $transaction->id,
            ];
        }

        return response()->json([
            'message' => count($generated) . ' transaction(s) générée(s)',
            'generated' => $generated,
        ]);
    }
}
