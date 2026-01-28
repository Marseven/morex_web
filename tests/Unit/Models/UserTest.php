<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->count(3)->for($user)->create();

        $this->assertCount(3, $user->accounts);
        $this->assertInstanceOf(Account::class, $user->accounts->first());
    }

    public function test_user_can_have_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(5)->for($user)->create();

        $this->assertCount(5, $user->categories);
        $this->assertInstanceOf(Category::class, $user->categories->first());
    }

    public function test_user_can_have_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->count(10)->for($user)->for($account)->create();

        $this->assertCount(10, $user->transactions);
        $this->assertInstanceOf(Transaction::class, $user->transactions->first());
    }

    public function test_user_can_have_goals(): void
    {
        $user = User::factory()->create();
        Goal::factory()->count(2)->for($user)->create();

        $this->assertCount(2, $user->goals);
        $this->assertInstanceOf(Goal::class, $user->goals->first());
    }

    public function test_user_can_have_debts(): void
    {
        $user = User::factory()->create();
        Debt::factory()->count(4)->for($user)->create();

        $this->assertCount(4, $user->debts);
        $this->assertInstanceOf(Debt::class, $user->debts->first());
    }

    public function test_user_total_balance_is_sum_of_all_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->withBalance(100000)->create();
        Account::factory()->for($user)->withBalance(200000)->create();
        Account::factory()->for($user)->withBalance(50000)->create();

        $this->assertEquals(350000, $user->total_balance);
    }

    public function test_user_total_balance_is_zero_with_no_accounts(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(0, $user->total_balance);
    }

    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plain-password',
        ]);

        $this->assertNotEquals('plain-password', $user->password);
    }

    public function test_user_has_default_theme(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->theme);
    }

    public function test_user_can_set_theme(): void
    {
        $user = User::factory()->create(['theme' => 'light']);

        $this->assertEquals('light', $user->theme);
    }
}
