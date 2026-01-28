<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_goals_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/goals');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Goals/Index'));
    }

    public function test_goals_page_shows_user_goals(): void
    {
        Goal::factory()->count(4)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/goals');

        $response->assertInertia(fn ($page) => $page
            ->component('Goals/Index')
            ->has('goals', 4)
        );
    }

    public function test_goals_page_shows_stats(): void
    {
        Goal::factory()->for($this->user)->active()->create([
            'target_amount' => 1000000,
            'current_amount' => 500000,
        ]);
        Goal::factory()->for($this->user)->completed()->create([
            'target_amount' => 500000,
        ]);

        $response = $this->actingAs($this->user)->get('/goals');

        $response->assertInertia(fn ($page) => $page
            ->has('stats')
            ->has('stats.total_target')
            ->has('stats.total_current')
            ->has('stats.active_count')
            ->has('stats.completed_count')
        );
    }

    public function test_goals_page_does_not_show_other_users_goals(): void
    {
        $otherUser = User::factory()->create();
        Goal::factory()->count(3)->for($otherUser)->create();
        Goal::factory()->count(2)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/goals');

        $response->assertInertia(fn ($page) => $page
            ->has('goals', 2)
        );
    }

    public function test_create_goal_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/goals/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Goals/Create')
            ->has('accounts')
        );
    }

    public function test_user_can_create_goal(): void
    {
        $response = $this->actingAs($this->user)->post('/goals', [
            'name' => 'Emergency Fund',
            'type' => 'savings',
            'target_amount' => 2610000,
            'current_amount' => 0,
            'target_date' => '2026-12-31',
            'color' => '#10B981',
            'icon' => 'shield',
        ]);

        $response->assertRedirect('/goals');
        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'name' => 'Emergency Fund',
            'target_amount' => 2610000,
            'status' => 'active',
        ]);
    }

    public function test_user_can_create_goal_with_initial_amount(): void
    {
        $response = $this->actingAs($this->user)->post('/goals', [
            'name' => 'Vacation',
            'type' => 'savings',
            'target_amount' => 500000,
            'current_amount' => 100000,
            'color' => '#3B82F6',
        ]);

        $response->assertRedirect('/goals');
        $this->assertDatabaseHas('goals', [
            'name' => 'Vacation',
            'target_amount' => 500000,
            'current_amount' => 100000,
        ]);
    }

    public function test_user_can_create_goal_linked_to_account(): void
    {
        $account = Account::factory()->for($this->user)->savings()->create();

        $response = $this->actingAs($this->user)->post('/goals', [
            'name' => 'Linked Goal',
            'type' => 'savings',
            'target_amount' => 300000,
            'current_amount' => 0,
            'account_id' => $account->id,
            'color' => '#FFFFFF',
        ]);

        $response->assertRedirect('/goals');
        $this->assertDatabaseHas('goals', [
            'name' => 'Linked Goal',
            'account_id' => $account->id,
        ]);
    }

    public function test_goal_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post('/goals', [
            'type' => 'savings',
            'target_amount' => 100000,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_goal_creation_requires_target_amount(): void
    {
        $response = $this->actingAs($this->user)->post('/goals', [
            'name' => 'Test Goal',
            'type' => 'savings',
        ]);

        $response->assertSessionHasErrors(['target_amount']);
    }

    public function test_edit_goal_page_can_be_rendered(): void
    {
        $goal = Goal::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get("/goals/{$goal->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Goals/Edit')
            ->has('goal')
            ->has('accounts')
        );
    }

    public function test_user_cannot_edit_other_users_goal(): void
    {
        $otherUser = User::factory()->create();
        $goal = Goal::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->get("/goals/{$goal->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_can_update_goal(): void
    {
        $goal = Goal::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->put("/goals/{$goal->id}", [
            'name' => 'Updated Goal',
            'type' => 'investment',
            'target_amount' => 2000000,
            'current_amount' => 500000,
            'target_date' => '2027-06-30',
            'status' => 'active',
            'color' => '#EF4444',
        ]);

        $response->assertRedirect('/goals');
        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'name' => 'Updated Goal',
            'target_amount' => 2000000,
        ]);
    }

    public function test_user_cannot_update_other_users_goal(): void
    {
        $otherUser = User::factory()->create();
        $goal = Goal::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->put("/goals/{$goal->id}", [
            'name' => 'Hacked Goal',
            'type' => 'savings',
            'target_amount' => 999999,
            'current_amount' => 0,
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_contribute_to_goal(): void
    {
        $goal = Goal::factory()->for($this->user)->create([
            'target_amount' => 100000,
            'current_amount' => 25000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post("/goals/{$goal->id}/contribute", [
            'amount' => 30000,
        ]);

        $response->assertRedirect();
        $goal->refresh();
        $this->assertEquals(55000, $goal->current_amount);
    }

    public function test_contribution_completes_goal_when_target_reached(): void
    {
        $goal = Goal::factory()->for($this->user)->create([
            'target_amount' => 100000,
            'current_amount' => 80000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post("/goals/{$goal->id}/contribute", [
            'amount' => 25000,
        ]);

        $response->assertRedirect();
        $goal->refresh();
        $this->assertEquals(105000, $goal->current_amount);
        $this->assertEquals('completed', $goal->status);
    }

    public function test_user_cannot_contribute_to_other_users_goal(): void
    {
        $otherUser = User::factory()->create();
        $goal = Goal::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->post("/goals/{$goal->id}/contribute", [
            'amount' => 10000,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_goal(): void
    {
        $goal = Goal::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->delete("/goals/{$goal->id}");

        $response->assertRedirect('/goals');
        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_user_cannot_delete_other_users_goal(): void
    {
        $otherUser = User::factory()->create();
        $goal = Goal::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->delete("/goals/{$goal->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('goals', ['id' => $goal->id]);
    }
}
