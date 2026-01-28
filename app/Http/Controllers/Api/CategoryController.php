<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/categories",
        summary: "Liste des catégories",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des catégories (personnelles + système)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Category")),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::where(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id)
                  ->orWhere('is_system', true);
        })
        ->orderBy('order_index')
        ->get();

        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: "/categories",
        summary: "Créer une catégorie",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "type"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Alimentation"),
                    new OA\Property(property: "type", type: "string", enum: ["expense", "income"]),
                    new OA\Property(property: "icon", type: "string", example: "shopping-cart"),
                    new OA\Property(property: "color", type: "string", example: "#10B981"),
                    new OA\Property(property: "parent_id", type: "string", format: "uuid", nullable: true),
                    new OA\Property(property: "budget_limit", type: "integer", nullable: true, example: 50000, description: "Budget mensuel en FCFA"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Catégorie créée", content: new OA\JsonContent(ref: "#/components/schemas/Category")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrder = $request->user()->categories()->max('order_index') ?? -1;

        $category = $request->user()->categories()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return response()->json(new CategoryResource($category), 201);
    }

    #[OA\Get(
        path: "/categories/{id}",
        summary: "Détail d'une catégorie",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détail de la catégorie", content: new OA\JsonContent(ref: "#/components/schemas/Category")),
            new OA\Response(response: 404, description: "Catégorie non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function show(Request $request, Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return new CategoryResource($category);
    }

    #[OA\Put(
        path: "/categories/{id}",
        summary: "Modifier une catégorie",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "type", type: "string", enum: ["expense", "income"]),
                    new OA\Property(property: "icon", type: "string"),
                    new OA\Property(property: "color", type: "string"),
                    new OA\Property(property: "parent_id", type: "string", format: "uuid", nullable: true),
                    new OA\Property(property: "budget_limit", type: "integer", nullable: true),
                    new OA\Property(property: "order_index", type: "integer"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Catégorie modifiée", content: new OA\JsonContent(ref: "#/components/schemas/Category")),
            new OA\Response(response: 422, description: "Erreur de validation"),
            new OA\Response(response: 403, description: "Catégorie système non modifiable"),
            new OA\Response(response: 404, description: "Catégorie non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function update(Request $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:expense,income'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'budget_limit' => ['nullable', 'integer', 'min:0'],
            'order_index' => ['sometimes', 'integer'],
        ]);

        $category->update($validated);

        return new CategoryResource($category->fresh());
    }

    #[OA\Delete(
        path: "/categories/{id}",
        summary: "Supprimer une catégorie",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string", format: "uuid")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Catégorie supprimée"),
            new OA\Response(response: 403, description: "Catégorie système non supprimable"),
            new OA\Response(response: 404, description: "Catégorie non trouvée"),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->is_system) {
            return response()->json([
                'message' => 'Les catégories système ne peuvent pas être supprimées.',
            ], 403);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/categories/reorder",
        summary: "Réordonner les catégories",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["categories"],
                properties: [
                    new OA\Property(
                        property: "categories",
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
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'uuid'],
            'categories.*.order_index' => ['required', 'integer'],
        ]);

        foreach ($validated['categories'] as $item) {
            Category::where('id', $item['id'])
                ->where('user_id', $request->user()->id)
                ->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
