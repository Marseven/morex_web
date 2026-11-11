<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Module dette-qualite-mobile (7b), P15 : rotation du jeton d'API.
 */
class TokenRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_issues_a_new_token_and_revokes_the_old_one(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('api-token', ['sync'])->plainTextToken;

        $response = $this->withToken($old)->postJson('/api/token/rotate')->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);
        $new = $response->json('token');

        $this->assertNotSame($old, $new);
        $this->assertNull(PersonalAccessToken::findToken($old));
        $this->assertSame(['sync'], PersonalAccessToken::findToken($new)->abilities);
        $this->app['auth']->forgetGuards();
        $this->withToken($new)->getJson('/api/user')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_rotation_requires_a_token(): void
    {
        $this->postJson('/api/token/rotate')->assertUnauthorized();
    }

    public function test_rotation_is_throttled(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        for ($i = 0; $i < 6; $i++) {
            $token = $this->withToken($token)->postJson('/api/token/rotate')->assertOk()->json('token');
            $this->app['auth']->forgetGuards();
        }

        $this->withToken($token)->postJson('/api/token/rotate')->assertStatus(429);
    }
}
