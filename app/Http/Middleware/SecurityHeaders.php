<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité posés sur toutes les réponses (web et API).
 *
 * La CSP reste volontairement non bloquante pour les scripts : une politique stricte casserait
 * Vite et Inertia. Elle interdit seulement l'encadrement, les <base> détournés et les plugins.
 */
final class SecurityHeaders
{
    private const HEADERS = [
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Ne pas annoncer la version de PHP (en-tête ajouté par l'interpréteur, hors de l'objet Response).
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
