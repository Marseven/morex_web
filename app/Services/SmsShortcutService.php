<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Jeton du Raccourci iOS (module sms-iphone 3b) : capacité unique « sms:webhook », un seul actif,
 * sans expiration (le Raccourci ne sait pas le renouveler), révocable depuis le Profil.
 */
final class SmsShortcutService
{
    public const TOKEN_NAME = 'sms-shortcut';

    public const ABILITY = 'sms:webhook';

    /** Émet un nouveau jeton (l'ancien est révoqué) ; le texte clair n'est rendu qu'ici, une seule fois. */
    public function issue(User $user): string
    {
        $this->revoke($user);

        return $user->createToken(self::TOKEN_NAME, [self::ABILITY])->plainTextToken;
    }

    public function revoke(User $user): void
    {
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();
    }

    /** @return array{active: bool, created_at: ?string, last_used_at: ?string} */
    public function status(User $user): array
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->tokens()->where('name', self::TOKEN_NAME)->latest()->first();

        return [
            'active' => $token !== null,
            'created_at' => $token?->created_at?->toIso8601String(),
            'last_used_at' => $token?->last_used_at?->toIso8601String(),
        ];
    }
}
