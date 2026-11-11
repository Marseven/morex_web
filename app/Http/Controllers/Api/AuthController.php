<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenRotator;
use App\Services\TwoFactorLoginToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/register',
        summary: 'Créer un nouveau compte utilisateur',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Richard Mebodo'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'richard@morex.app'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
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
        path: '/login',
        summary: 'Connexion utilisateur',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'richard@morex.app'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Identifiants incorrects', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function login(Request $request, TwoFactorLoginToken $tokens): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Check if 2FA is enabled
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'two_factor_required' => true,
                'login_token' => $tokens->issue($user),
                'email' => $user->email,
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    #[OA\Post(
        path: '/two-factor-challenge',
        summary: 'Vérification du code 2FA',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login_token'],
                properties: [
                    new OA\Property(property: 'login_token', type: 'string', description: 'Jeton renvoyé par /login, valable 5 minutes'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                    new OA\Property(property: 'recovery_code', type: 'string', example: 'abcd-efgh'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA validé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Code invalide'),
        ]
    )]
    public function twoFactorChallenge(Request $request, TwoFactorLoginToken $tokens): JsonResponse
    {
        $validated = $request->validate([
            'login_token' => ['required', 'string', 'max:2048'],
            // Typage strict : un booléen ou un tableau ne doit jamais atteindre la comparaison
            'code' => ['nullable', 'bail', 'string', 'digits:6'],
            'recovery_code' => ['nullable', 'bail', 'string', 'max:64'],
        ]);

        $user = $tokens->resolve($validated['login_token']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'login_token' => ['Session de connexion invalide ou expirée. Reconnectez-vous.'],
            ]);
        }

        // Verify with TOTP code
        if (! empty($validated['code'])) {
            $google2fa = new \PragmaRX\Google2FA\Google2FA;
            $secret = decrypt($user->two_factor_secret);

            if (! $google2fa->verifyKey($secret, $validated['code'])) {
                throw ValidationException::withMessages([
                    'code' => ['Le code est invalide.'],
                ]);
            }
        }
        // Verify with recovery code
        elseif (! empty($validated['recovery_code'])) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
            $matchedIndex = $this->findRecoveryCode($recoveryCodes, $validated['recovery_code']);

            if ($matchedIndex === null) {
                throw ValidationException::withMessages([
                    'recovery_code' => ['Le code de récupération est invalide.'],
                ]);
            }

            // Usage unique : on retire exactement le code consommé
            unset($recoveryCodes[$matchedIndex]);
            $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
            $user->save();
        } else {
            throw ValidationException::withMessages([
                'code' => ['Veuillez fournir un code d\'authentification ou un code de récupération.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'two_factor_enabled' => true,
        ]);
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Déconnexion utilisateur',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Déconnexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Déconnexion réussie.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
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
        path: '/user',
        summary: "Obtenir l'utilisateur connecté",
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Informations utilisateur',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Comparaison stricte et à temps constant : renvoie l'index du code trouvé, ou null.
     *
     * @param  array<int, mixed>  $recoveryCodes
     */
    private function findRecoveryCode(array $recoveryCodes, string $candidate): ?int
    {
        foreach ($recoveryCodes as $index => $code) {
            if (is_string($code) && hash_equals($code, $candidate)) {
                return $index;
            }
        }

        return null;
    }

    /** Rotation du jeton d'API (module dette-qualite-mobile, P15) : voir ApiTokenRotator. */
    public function rotateToken(Request $request, ApiTokenRotator $rotator): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        // Seul un vrai jeton d'API se tourne (jamais une session web)
        abort_unless($current instanceof PersonalAccessToken, 403);

        $new = $rotator->rotate($request->user(), $current);
        abort_if($new === null, 401, 'Jeton déjà renouvelé.');

        return response()->json([
            'token' => $new->plainTextToken,
            'expires_at' => $new->accessToken->expires_at?->toIso8601String(),
        ]);
    }
}
