<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rotation du jeton d'API (module dette-qualite-mobile, P15).
 *
 * - Échéance absolue : le nouveau jeton garde l'échéance du jeton de connexion ; une rotation ne la repousse
 *   jamais (un jeton volé ne survit pas au-delà de 30 jours après la connexion).
 * - Délai de grâce : l'ancien jeton reste valable GRACE_MINUTES, au cas où la réponse se perd en route.
 * - Révocation conditionnelle : un ancien jeton déjà tourné ne produit pas un second jeton (rejeu refusé).
 */
final class ApiTokenRotator
{
    public const GRACE_MINUTES = 2;

    /** Null si le jeton a déjà été tourné (ou expire dans le délai de grâce). */
    public function rotate(User $user, PersonalAccessToken $current): ?NewAccessToken
    {
        $graceEnd = now()->addMinutes(self::GRACE_MINUTES);
        $deadline = $this->absoluteExpiry($current);

        return DB::transaction(function () use ($user, $current, $graceEnd, $deadline) {
            // Compare-and-set : seul le premier appel passe ; l'ancien jeton entre en délai de grâce
            $claimed = PersonalAccessToken::whereKey($current->getKey())
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $graceEnd))
                ->update(['expires_at' => $deadline !== null && $deadline->lt($graceEnd) ? $deadline : $graceEnd]);
            if ($claimed !== 1) {
                return null;
            }

            return $user->createToken($current->name, $current->abilities ?? ['*'], $deadline);
        });
    }

    /** Révoque les jetons d'un utilisateur encore en délai de grâce (appelé à la déconnexion). */
    public function revokeGracePeriodTokens(User $user): void
    {
        $user->tokens()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addMinutes(self::GRACE_MINUTES))
            ->delete();
    }

    /** Échéance absolue du jeton : la sienne, sinon création + durée Sanctum, sinon aucune. */
    private function absoluteExpiry(PersonalAccessToken $token): ?CarbonInterface
    {
        if ($token->expires_at !== null) {
            return $token->expires_at;
        }
        $minutes = config('sanctum.expiration');

        return $minutes ? $token->created_at->copy()->addMinutes((int) $minutes) : null;
    }
}
