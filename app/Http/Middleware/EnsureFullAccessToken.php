<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SmsShortcutService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module sms-iphone : un jeton restreint (ex. celui du Raccourci SMS, capacité « sms:webhook ») ne sert
 * qu'aux routes qui le déclarent. Partout ailleurs, seule la capacité complète « * » est acceptée.
 */
final class EnsureFullAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        // Refuse un jeton restreint au Raccourci (capacité « sms:webhook » sans « * »)
        if ($token instanceof PersonalAccessToken && $token->can(SmsShortcutService::ABILITY) && ! $token->can('*')) {
            abort(403, 'Ce jeton est réservé au Raccourci SMS.');
        }

        return $next($request);
    }
}
