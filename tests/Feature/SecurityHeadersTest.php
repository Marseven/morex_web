<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * socle-prod : chaque réponse, web comme API, porte les en-têtes de sécurité.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function urls(): array
    {
        return [
            'page web' => ['/login'],
            'api' => ['/api/user'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('urls')]
    public function test_responses_carry_security_headers(string $url): void
    {
        $response = $this->get($url, ['Accept' => 'application/json']);

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }
}
