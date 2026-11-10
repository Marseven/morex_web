<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * socle-prod RM-1 : en production, une erreur ne doit jamais exposer de trace,
 * même si APP_DEBUG=true a été oublié dans le .env.
 */
class ProductionDebugGuardTest extends TestCase
{
    private function bootAs(string $environment): void
    {
        $this->app['env'] = $environment;
        config(['app.debug' => true]);
        (new AppServiceProvider($this->app))->boot();

        Route::get('/__boom', fn () => throw new \RuntimeException('secret detail'));
    }

    public function test_production_errors_never_expose_a_stack_trace(): void
    {
        $this->bootAs('production');

        $response = $this->getJson('/__boom');

        $response->assertStatus(500)
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
        $this->assertStringNotContainsString('secret detail', $response->getContent());
    }

    public function test_local_environment_keeps_debug_details(): void
    {
        $this->bootAs('local');

        $this->getJson('/__boom')->assertStatus(500)->assertJsonPath('message', 'secret detail');
    }
}
