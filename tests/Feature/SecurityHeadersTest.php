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
            'page web' => ['https://localhost/login'],
            'api' => ['https://localhost/api/user'],
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

    public function test_https_only_directives_are_skipped_over_plain_http(): void
    {
        // En développement (MAMP, Vite en http), HSTS et upgrade-insecure-requests casseraient les assets.
        $response = $this->get('http://localhost/login');

        $response->assertHeaderMissing('Strict-Transport-Security');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringNotContainsString('upgrade-insecure-requests', (string) $response->headers->get('Content-Security-Policy'));
    }
}
