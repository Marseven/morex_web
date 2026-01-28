<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AccountController extends Controller
{
    #[OA\Get(
        path: "/accounts",
        summary: "Liste des comptes",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des comptes de l'utilisateur",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Account")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()
            ->accounts()
            ->orderBy('order_index')
            ->get();

        return AccountResource::collection($accounts);
    }

    #[OA\Post(
        path: "/accounts",
        summary: "Créer un compte",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "type", "initial_balance"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Compte épargne"),
                    new OA\Property(property: "type", type: "string", enum: ["checking", "savings", "cash", "credit", "investment"]),
                    new OA\Property(property: "initial_balance", type: "integer", example: 100000),
                    new OA\Property(property: "color", type: "string", example: "#3B82F6"),
                    new OA\Property(property: "icon", type: "string", example: "wallet"),
                    new OA\Property(property: "is_default", type: "boolean", example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Compte créé", content: new OA\JsonContent(ref: "#/components/schemas/Account")),
            new OA\Response(response: 422, description: "Erreur de validation", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:checking,savings,cash,credit,investment'],
            'initial_balance' => ['required', 'integer'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
        ]);

        $maxOrder = $request->user()->accounts()->max('order_index') ?? -1;

        $account = $request->user()->accounts()->create([
            ...$validated,
            'balance' => $validated['initial_balance'],
            'order_index' => $maxOrder + 1,
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        return response()->json(new AccountResource($account), 201);
    }

    #[OA\Get(
        path: "/accounts/{id}",
        summary: "Détail d'un compte",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail du compte", content: new OA\JsonContent(ref: "#/components/schemas/Account")),
            new OA\Response(response: 404, description: "Compte non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }

    #[OA\Put(
        path: "/accounts/{id}",
        summary: "Modifier un compte",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "type", type: "string", enum: ["checking", "savings", "cash", "credit", "investment"]),
                    new OA\Property(property: "initial_balance", type: "integer"),
                    new OA\Property(property: "color", type: "string"),
                    new OA\Property(property: "icon", type: "string"),
                    new OA\Property(property: "is_default", type: "boolean"),
                    new OA\Property(property: "order_index", type: "integer"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Compte modifié", content: new OA\JsonContent(ref: "#/components/schemas/Account")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 404, description: "Compte non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, Account $account): AccountResource
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:checking,savings,cash,credit,investment'],
            'initial_balance' => ['sometimes', 'integer'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
            'order_index' => ['sometimes', 'integer'],
        ]);

        $account->update($validated);

        if ($validated['is_default'] ?? false) {
            $request->user()->accounts()
                ->where('id', '!=', $account->id)
                ->update(['is_default' => false]);
        }

        if (isset($validated['initial_balance'])) {
            $account->recalculateBalance();
        }

        return new AccountResource($account->fresh());
    }

    #[OA\Delete(
        path: "/accounts/{id}",
        summary: "Supprimer un compte",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Compte supprimé"),
            new OA\Response(response: 404, description: "Compte non trouvé"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/accounts/reorder",
        summary: "Réordonner les comptes",
        tags: ["Accounts"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["accounts"],
                properties: [
                    new OA\Property(
                        property: "accounts",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "order_index", type: "integer"),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Ordre mis à jour"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accounts' => ['required', 'array'],
            'accounts.*.id' => ['required', 'uuid'],
            'accounts.*.order_index' => ['required', 'integer'],
        ]);

        foreach ($validated['accounts'] as $item) {
            $request->user()->accounts()
                ->where('id', $item['id'])
                ->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
