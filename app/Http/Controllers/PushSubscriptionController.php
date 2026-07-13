<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Enregistre (ou met à jour) l'abonnement Web Push du navigateur courant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Dédup applicative : un seul enregistrement par endpoint pour cet utilisateur.
        $user->pushSubscriptions()
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        $subscription = $user->pushSubscriptions()->create([
            'endpoint' => $validated['endpoint'],
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
        ]);

        return response()->json(['id' => $subscription->id], 201);
    }

    /**
     * Désabonne le navigateur courant.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->pushSubscriptions()
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['deleted' => true]);
    }
}
