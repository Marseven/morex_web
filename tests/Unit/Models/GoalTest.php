<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        $this->assertInstanceOf(User::class, $goal->user);
        $this->assertEquals($user->id, $goal->user->id);
    }

    public function test_goal_has_uuid_as_primary_key(): void
    {
        $goal = Goal::factory()->create();

        $this->assertIsString($goal->id);
        $this->assertEquals(36, strlen($goal->id));
    }

    public function test_goal_can_be_linked_to_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $goal = Goal::factory()
            ->for($user)
            ->state(['account_id' => $account->id])
            ->create();

        $this->assertInstanceOf(Account::class, $goal->account);
        $this->assertEquals($account->id, $goal->account->id);
    }

    public function test_goal_progress_percentage(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 25000,
        ]);

        $this->assertEquals(25.0, $goal->progress_percentage);
    }

    public function test_goal_progress_percentage_is_capped_at_100(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 150000,
        ]);

        $this->assertEquals(100.0, $goal->progress_percentage);
    }

    public function test_goal_progress_percentage_is_zero_with_zero_target(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 0,
            'current_amount' => 0,
        ]);

        $this->assertEquals(0, $goal->progress_percentage);
    }

    public function test_goal_remaining_amount(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 25000,
        ]);

        $this->assertEquals(75000, $goal->remaining_amount);
    }

    public function test_goal_remaining_amount_is_zero_when_completed(): void
    {
        $goal = Goal::factory()->completed()->create([
            'target_amount' => 100000,
        ]);

        $this->assertEquals(0, $goal->remaining_amount);
    }

    public function test_goal_days_remaining(): void
    {
        $goal = Goal::factory()->create([
            'target_date' => Carbon::now()->addDays(30),
        ]);

        // Allow for time differences (29-30 days depending on exact time)
        $this->assertGreaterThanOrEqual(29, $goal->days_remaining);
        $this->assertLessThanOrEqual(30, $goal->days_remaining);
    }

    public function test_goal_days_remaining_is_null_without_target_date(): void
    {
        $goal = Goal::factory()->create(['target_date' => null]);

        $this->assertNull($goal->days_remaining);
    }

    public function test_goal_days_remaining_is_zero_when_past(): void
    {
        $goal = Goal::factory()->create([
            'target_date' => Carbon::now()->subDays(5),
        ]);

        $this->assertEquals(0, $goal->days_remaining);
    }

    public function test_goal_is_completed_attribute(): void
    {
        $incompleteGoal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 50000,
        ]);

        $completeGoal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 100000,
        ]);

        $this->assertFalse($incompleteGoal->is_completed);
        $this->assertTrue($completeGoal->is_completed);
    }

    public function test_goal_add_amount(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 25000,
            'status' => 'active',
        ]);

        $goal->addAmount(30000);

        $this->assertEquals(55000, $goal->current_amount);
        $this->assertEquals('active', $goal->status);
    }

    public function test_goal_add_amount_completes_goal_when_target_reached(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 80000,
            'status' => 'active',
        ]);

        $goal->addAmount(30000);

        $this->assertEquals(110000, $goal->current_amount);
        $this->assertEquals('completed', $goal->status);
    }

    public function test_goal_statuses(): void
    {
        $activeGoal = Goal::factory()->active()->create();
        $completedGoal = Goal::factory()->completed()->create();

        $this->assertEquals('active', $activeGoal->status);
        $this->assertEquals('completed', $completedGoal->status);
    }

    public function test_goal_types(): void
    {
        $savingsGoal = Goal::factory()->create(['type' => 'savings']);
        $investmentGoal = Goal::factory()->create(['type' => 'investment']);
        $customGoal = Goal::factory()->create(['type' => 'custom']);

        $this->assertEquals('savings', $savingsGoal->type);
        $this->assertEquals('investment', $investmentGoal->type);
        $this->assertEquals('custom', $customGoal->type);
    }

    public function test_goal_casts_amounts_to_integer(): void
    {
        $goal = Goal::factory()->create([
            'target_amount' => 100000,
            'current_amount' => 50000,
        ]);

        $this->assertIsInt($goal->target_amount);
        $this->assertIsInt($goal->current_amount);
    }

    public function test_goal_casts_target_date(): void
    {
        $goal = Goal::factory()->create([
            'target_date' => '2026-12-31',
        ]);

        $this->assertInstanceOf(Carbon::class, $goal->target_date);
    }
}
