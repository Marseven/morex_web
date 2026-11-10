<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Jeton court émis après un mot de passe valide, exigé par le challenge 2FA de l'API.
 *
 * Il prouve que l'étape mot de passe a eu lieu : sans lui, le challenge 2FA
 * remplacerait le mot de passe au lieu de s'y ajouter. Il expire après TTL_SECONDS
 * et devient invalide si le mot de passe change entre-temps.
 */
final class TwoFactorLoginToken
{
    public const TTL_SECONDS = 300;

    private const PURPOSE = '2fa-login';

    public function issue(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => self::PURPOSE,
            'uid' => $user->getKey(),
            'exp' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
            'pwd' => $this->passwordFingerprint($user),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Retourne l'utilisateur si le jeton est authentique, non expiré et toujours lié
     * au mot de passe courant ; null sinon (sans distinguer la cause).
     */
    public function resolve(string $token): ?User
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['purpose'] ?? null) !== self::PURPOSE
            || ! is_int($payload['exp'] ?? null)
            || $payload['exp'] < now()->getTimestamp()) {
            return null;
        }

        $user = User::find($payload['uid'] ?? null);

        if (! $user || ! hash_equals($this->passwordFingerprint($user), (string) ($payload['pwd'] ?? ''))) {
            return null;
        }

        return $user;
    }

    private function passwordFingerprint(User $user): string
    {
        return hash_hmac('sha256', (string) $user->password, (string) config('app.key'));
    }
}
