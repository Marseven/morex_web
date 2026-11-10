<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * socle-prod : la documentation de l'API n'est plus publique.
 */
class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/api/documentation')->assertRedirect('/login');
    }

    public function test_signed_in_user_can_read_the_documentation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/api/documentation')
            ->assertOk();
    }

    public function test_web_sessions_expire_after_thirty_minutes_by_default(): void
    {
        $this->assertSame(30, config('session.lifetime'));
    }
}
