<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DebtResource;
use App\Models\Debt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class DebtController extends Controller
{
    #[OA\Get(
        path: "/debts",
        summary: "Liste des dettes et créances",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["debt", "credit"]), description: "debt = je dois, credit = on me doit"),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "paid", "cancelled"])),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer", default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des dettes/créances",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Debt")),
                        new OA\Property(property: "meta", ref: "#/components/schemas/PaginationMeta"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->debts();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $debts = $query->orderByRaw("FIELD(status, 'active', 'paid', 'cancelled')")
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return DebtResource::collection($debts);
    }

    #[OA\Post(
        path: "/debts",
        summary: "Créer une dette ou créance",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "type", "initial_amount"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Prêt famille"),
                    new OA\Property(property: "type", type: "string", enum: ["debt", "credit"], description: "debt = je dois, credit = on me doit"),
                    new OA\Property(property: "initial_amount", type: "integer", example: 100000),
                    new OA\Property(property: "current_amount", type: "integer", nullable: true, description: "Si différent du montant initial"),
                    new OA\Property(property: "due_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "contact_name", type: "string", nullable: true, example: "Papa"),
                    new OA\Property(property: "contact_phone", type: "string", nullable: true, example: "+241 77 12 34 56"),
                    new OA\Property(property: "color", type: "string", example: "#EF4444"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Dette/créance créée", content: new OA\JsonContent(ref: "#/components/schemas/Debt")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:debt,credit'],
            'initial_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['nullable', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $debt = $request->user()->debts()->create([
            ...$validated,
            'current_amount' => $validated['current_amount'] ?? $validated['initial_amount'],
            'status' => 'active',
        ]);

        return response()->json(new DebtResource($debt), 201);
    }

    #[OA\Get(
        path: "/debts/{id}",
        summary: "Détail d'une dette/créance",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail", content: new OA\JsonContent(ref: "#/components/schemas/Debt")),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('view', $debt);

        return new DebtResource($debt);
    }

    #[OA\Put(
        path: "/debts/{id}",
        summary: "Modifier une dette/créance",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "type", type: "string", enum: ["debt", "credit"]),
                    new OA\Property(property: "initial_amount", type: "integer"),
                    new OA\Property(property: "current_amount", type: "integer"),
                    new OA\Property(property: "due_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "contact_name", type: "string", nullable: true),
                    new OA\Property(property: "contact_phone", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "paid", "cancelled"]),
                    new OA\Property(property: "color", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Modifié", content: new OA\JsonContent(ref: "#/components/schemas/Debt")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('update', $debt);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:debt,credit'],
            'initial_amount' => ['sometimes', 'integer', 'min:1'],
            'current_amount' => ['sometimes', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,paid,cancelled'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $debt->update($validated);

        return new DebtResource($debt->fresh());
    }

    #[OA\Delete(
        path: "/debts/{id}",
        summary: "Supprimer une dette/créance",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Supprimé"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, Debt $debt): JsonResponse
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/debts/{id}/payment",
        summary: "Enregistrer un paiement",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount"],
                properties: [
                    new OA\Property(property: "amount", type: "integer", example: 25000, description: "Montant du paiement en FCFA"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Paiement enregistré", content: new OA\JsonContent(ref: "#/components/schemas/Debt")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function payment(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('update', $debt);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $debt->addPayment($validated['amount']);

        return new DebtResource($debt->fresh());
    }

    #[OA\Get(
        path: "/debts-stats",
        summary: "Statistiques des dettes et créances",
        tags: ["Debts"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "total_debt", type: "integer", description: "Total des dettes actives"),
                        new OA\Property(property: "total_credit", type: "integer", description: "Total des créances actives"),
                        new OA\Property(property: "active_debts", type: "integer"),
                        new OA\Property(property: "active_credits", type: "integer"),
                        new OA\Property(property: "overdue_count", type: "integer"),
                        new OA\Property(property: "net_position", type: "integer", description: "créances - dettes"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $debts = $request->user()->debts;

        return response()->json([
            'total_debt' => $debts->where('type', 'debt')->where('status', 'active')->sum('current_amount'),
            'total_credit' => $debts->where('type', 'credit')->where('status', 'active')->sum('current_amount'),
            'active_debts' => $debts->where('type', 'debt')->where('status', 'active')->count(),
            'active_credits' => $debts->where('type', 'credit')->where('status', 'active')->count(),
            'overdue_count' => $debts->where('status', 'active')->filter(fn($d) => $d->is_overdue)->count(),
            'net_position' => $debts->where('type', 'credit')->where('status', 'active')->sum('current_amount')
                           - $debts->where('type', 'debt')->where('status', 'active')->sum('current_amount'),
        ]);
    }
}
