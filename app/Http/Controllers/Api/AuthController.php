<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/register",
        summary: "Créer un nouveau compte utilisateur",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Richard Mebodo"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "richard@morex.app"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Compte créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "token", type: "string", example: "1|abcdef123456..."),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    #[OA\Post(
        path: "/login",
        summary: "Connexion utilisateur",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "richard@morex.app"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Connexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "token", type: "string", example: "1|abcdef123456..."),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Identifiants incorrects", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: "/logout",
        summary: "Déconnexion utilisateur",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Déconnexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Déconnexion réussie."),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    #[OA\Get(
        path: "/user",
        summary: "Obtenir l'utilisateur connecté",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Informations utilisateur",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
        ]
    )]
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
