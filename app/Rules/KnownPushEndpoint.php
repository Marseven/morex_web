<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Adresse d'abonnement Web Push : HTTPS vers un service push connu (anti-SSRF).
 * Le serveur envoie des requêtes à cette adresse : une adresse arbitraire ferait de lui un relais.
 */
final class KnownPushEndpoint implements ValidationRule
{
    /** Hôtes exacts, ou suffixes précédés d'un point (sous-domaines). */
    private const HOSTS = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'updates.push.services.mozilla.com',
        '.push.services.mozilla.com',
        'web.push.apple.com',
        '.push.apple.com',
        '.notify.windows.com',
    ];

    public static function allows(?string $endpoint): bool
    {
        if (! is_string($endpoint) || strlen($endpoint) > 2048) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');

        foreach (self::HOSTS as $allowed) {
            $match = str_starts_with($allowed, '.') ? str_ends_with($host, $allowed) : $host === $allowed;
            if ($match) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::allows(is_string($value) ? $value : null)) {
            $fail('Service de notification non reconnu.');
        }
    }
}
