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
        $this->assertTrue(PersonalAccessToken::findToken($old)->expires_at->lte(now()->addMinutes(2))); // délai de grâce
        $this->assertSame(['sync'], PersonalAccessToken::findToken($new)->abilities);
        $this->app['auth']->forgetGuards();
        $this->withToken($new)->getJson('/api/user')->assertOk();
        $this->travel(3)->minutes();
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

    // --- Revue de sécurité ---

    public function test_rotation_never_extends_the_absolute_expiry(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(9, 0));
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $deadline = now()->addMinutes((int) config('sanctum.expiration'));

        foreach ([8, 16, 24] as $day) {
            $this->travelTo(now()->setDate(2026, 11, 1 + $day));
            $token = $this->withToken($token)->postJson('/api/token/rotate')->assertOk()->json('token');
            $this->app['auth']->forgetGuards();
            $this->assertTrue(PersonalAccessToken::findToken($token)->expires_at->equalTo($deadline));
        }

        $this->travelTo($deadline->copy()->addMinute());
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_old_token_survives_a_lost_response_for_two_minutes(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('api-token')->plainTextToken;

        $this->withToken($old)->postJson('/api/token/rotate')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($old)->getJson('/api/user')->assertOk();
    }

    public function test_a_replayed_rotation_issues_no_second_token(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('api-token')->plainTextToken;

        $this->withToken($old)->postJson('/api/token/rotate')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->postJson('/api/token/rotate')->assertUnauthorized();

        $this->assertSame(2, $user->tokens()->count()); // l'ancien en grâce, un seul nouveau
    }
}
