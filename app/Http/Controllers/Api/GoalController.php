<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class GoalController extends Controller
{
    #[OA\Get(
        path: "/goals",
        summary: "Liste des objectifs",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "completed", "cancelled"])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des objectifs",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Goal")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->goals()->with('account');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $goals = $query->orderByDesc('created_at')->get();

        return GoalResource::collection($goals);
    }

    #[OA\Post(
        path: "/goals",
        summary: "Créer un objectif",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "type", "target_amount"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Fonds d'urgence"),
                    new OA\Property(property: "type", type: "string", enum: ["savings", "debt", "investment", "custom"]),
                    new OA\Property(property: "target_amount", type: "integer", example: 2610000, description: "Montant cible en FCFA"),
                    new OA\Property(property: "current_amount", type: "integer", example: 0, description: "Montant déjà épargné"),
                    new OA\Property(property: "target_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "account_id", type: "string", format: "uuid", nullable: true, description: "Compte associé pour suivi auto"),
                    new OA\Property(property: "color", type: "string", example: "#8B5CF6"),
                    new OA\Property(property: "icon", type: "string", example: "target"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Objectif créé", content: new OA\JsonContent(ref: "#/components/schemas/Goal")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:savings,debt,investment,custom'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'current_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $goal = $request->user()->goals()->create([
            ...$validated,
            'current_amount' => $validated['current_amount'] ?? 0,
            'status' => 'active',
        ]);

        return response()->json(new GoalResource($goal->load('account')), 201);
    }

    #[OA\Get(
        path: "/goals/{id}",
        summary: "Détail d'un objectif",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail de l'objectif", content: new OA\JsonContent(ref: "#/components/schemas/Goal")),
            new OA\Response(response: 404, description: "Objectif non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, Goal $goal): GoalResource
    {
        $this->authorize('view', $goal);

        return new GoalResource($goal->load('account'));
    }

    #[OA\Put(
        path: "/goals/{id}",
        summary: "Modifier un objectif",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "type", type: "string", enum: ["savings", "debt", "investment", "custom"]),
                    new OA\Property(property: "target_amount", type: "integer"),
                    new OA\Property(property: "current_amount", type: "integer"),
                    new OA\Property(property: "target_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "account_id", type: "string", format: "uuid", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "completed", "cancelled"]),
                    new OA\Property(property: "color", type: "string"),
                    new OA\Property(property: "icon", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Objectif modifié", content: new OA\JsonContent(ref: "#/components/schemas/Goal")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Objectif non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, Goal $goal): GoalResource
    {
        $this->authorize('update', $goal);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:savings,debt,investment,custom'],
            'target_amount' => ['sometimes', 'integer', 'min:1'],
            'current_amount' => ['sometimes', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'uuid', 'exists:accounts,id'],
            'status' => ['sometimes', 'in:active,completed,cancelled'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $goal->update($validated);

        if (isset($validated['current_amount']) && $validated['current_amount'] >= $goal->target_amount) {
            $goal->update(['status' => 'completed']);
        }

        return new GoalResource($goal->fresh()->load('account'));
    }

    #[OA\Delete(
        path: "/goals/{id}",
        summary: "Supprimer un objectif",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Objectif supprimé"),
            new OA\Response(response: 404, description: "Objectif non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/goals/{id}/contribute",
        summary: "Ajouter une contribution à un objectif",
        tags: ["Goals"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount"],
                properties: [
                    new OA\Property(property: "amount", type: "integer", example: 50000, description: "Montant à ajouter en FCFA"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Contribution ajoutée", content: new OA\JsonContent(ref: "#/components/schemas/Goal")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Objectif non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function addContribution(Request $request, Goal $goal): GoalResource
    {
        $this->authorize('update', $goal);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $goal->addAmount($validated['amount']);

        return new GoalResource($goal->fresh()->load('account'));
    }
}
