<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module suivi-objectifs (5a) : RM-1 progression suivie, RM-2 versement du mois, RM-3 projection, RM-5.
 */
class GoalTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $savings;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $this->user = User::factory()->create();
        $this->savings = Account::factory()->for($this->user)->withBalance(5200000)->create(['name' => 'Compte Epargne']);
        $this->goal = Goal::factory()->for($this->user)->create([
            'name' => 'Épargne Investissement', 'type' => 'savings', 'target_amount' => 2000000,
            'current_amount' => 390000, 'target_date' => '2026-12-31', 'status' => 'active', 'account_id' => null,
        ]);
    }

    private function track(int $start = 990000, int $monthly = 300000): void
    {
        $this->actingAs($this->user)->post("/goals/{$this->goal->id}/track", [
            'account_id' => $this->savings->id, 'start_amount' => $start, 'monthly_target' => $monthly,
        ])->assertRedirect();
        $this->goal->refresh();
    }

    public function test_tracking_starts_from_the_chosen_amount(): void
    {
        $this->track(990000);

        $this->assertSame(990000, $this->goal->current_amount);
        $this->assertSame($this->savings->id, $this->goal->account_id);
    }

    public function test_progress_follows_the_savings_account_balance(): void
    {
        $this->track(990000);

        $this->savings->adjustBalance(300000, 'transfer_in', 'transfer', null, 'Versement épargne');
        $this->assertSame(1290000, $this->goal->refresh()->current_amount);

        $this->savings->adjustBalance(-700000, 'transfer_out', 'transfer', null, 'Avance ordinateur');
        $this->assertSame(590000, $this->goal->refresh()->current_amount);
    }

    public function test_progress_never_goes_below_zero(): void
    {
        $this->track(100000);
        $this->savings->adjustBalance(-500000, 'transfer_out', 'transfer', null, 'Retrait');

        $this->assertSame(0, $this->goal->refresh()->current_amount);
    }

    public function test_an_untracked_goal_keeps_its_manual_progress(): void
    {
        $this->savings->adjustBalance(300000, 'transfer_in', 'transfer', null, 'X');

        $this->assertSame(390000, $this->goal->refresh()->current_amount);
    }

    public function test_the_month_payment_is_measured_since_the_active_cycle_start(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->savings->adjustBalance(50000, 'transfer_in', 'transfer', null, 'Avant le cycle');
        $this->travelTo(now()->setDate(2026, 11, 11));
        $cycle = new BudgetCycle(['start_date' => '2026-11-05', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();
        $this->track();
        $this->savings->adjustBalance(200000, 'transfer_in', 'transfer', null, 'Versement');

        $status = app(GoalProgressService::class)->status($this->goal->refresh());

        $this->assertSame(200000, $status['month_paid']);
        $this->assertSame('remaining', $status['month_status']);
        $this->assertSame(100000, $status['month_remaining']);
    }

    public function test_the_month_is_late_after_the_tenth_day_without_enough_payment(): void
    {
        $this->track();
        $this->travelTo(now()->setDate(2026, 11, 15));

        $this->assertSame('late', app(GoalProgressService::class)->status($this->goal->refresh())['month_status']);
    }

    public function test_the_projection_tells_how_much_per_month_is_needed(): void
    {
        $this->track(1590000, 300000);

        $status = app(GoalProgressService::class)->status($this->goal->refresh());

        // Reste 410 000 sur 2 mois (novembre, décembre)
        $this->assertSame(205000, $status['required_per_month']);
        $this->assertTrue($status['on_track']);
    }

    public function test_contributing_to_a_tracked_goal_is_refused(): void
    {
        $this->track();

        $this->actingAs($this->user)->post("/goals/{$this->goal->id}/contribute", ['amount' => 1000])
            ->assertSessionHasErrors('amount');
        $this->assertSame(990000, $this->goal->refresh()->current_amount);
    }

    public function test_untracking_freezes_the_last_computed_value(): void
    {
        $this->track(990000);
        $this->savings->adjustBalance(300000, 'transfer_in', 'transfer', null, 'X');

        $this->actingAs($this->user)->delete("/goals/{$this->goal->id}/track")->assertRedirect();

        $this->savings->adjustBalance(100000, 'transfer_in', 'transfer', null, 'Après');
        $this->assertSame(1290000, $this->goal->refresh()->current_amount);
        $this->assertNull($this->goal->tracked_since);
    }

    public function test_tracking_refuses_another_users_goal_or_account(): void
    {
        $this->actingAs(User::factory()->create())->post("/goals/{$this->goal->id}/track", [
            'account_id' => $this->savings->id, 'start_amount' => 0,
        ])->assertForbidden();

        $foreign = Account::factory()->for(User::factory())->create();
        $this->actingAs($this->user)->post("/goals/{$this->goal->id}/track", [
            'account_id' => $foreign->id, 'start_amount' => 0,
        ])->assertSessionHasErrors('account_id');

        $this->actingAs($this->user)->post("/goals/{$this->goal->id}/track", [
            'account_id' => $this->savings->id, 'start_amount' => -5,
        ])->assertSessionHasErrors('start_amount');
    }

    public function test_the_api_can_track_and_returns_the_computed_progress(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/goals/{$this->goal->id}/track", ['account_id' => $this->savings->id, 'start_amount' => 990000])
            ->assertOk()
            ->assertJsonPath('data.current_amount', 990000)
            ->assertJsonPath('data.is_tracked', true);
    }

    public function test_sync_always_sends_tracked_goals_and_ignores_pushed_progress(): void
    {
        $this->track(990000);
        Sanctum::actingAs($this->user);
        $this->travel(1)->hours();
        $this->savings->adjustBalance(300000, 'transfer_in', 'transfer', null, 'X');

        $since = now()->subMinutes(10)->toIso8601String();
        $this->getJson('/api/sync/pull?last_sync='.urlencode($since))
            ->assertOk()
            ->assertJsonPath('goals.0.current_amount', 1290000);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'goal', 'action' => 'update', 'local_id' => 'g', 'server_id' => $this->goal->id,
            'data' => ['current_amount' => 5], 'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();
        $this->assertSame(1290000, $this->goal->refresh()->current_amount);
    }

    public function test_the_dashboard_shows_the_tracked_goal(): void
    {
        $this->track(1590000);

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('savingsGoal.current_amount', 1590000)
                ->where('savingsGoal.target_amount', 2000000)
                ->has('savingsGoal.month_status')
            );
    }
}
