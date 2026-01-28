<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->assertInstanceOf(User::class, $category->user);
        $this->assertEquals($user->id, $category->user->id);
    }

    public function test_category_has_uuid_as_primary_key(): void
    {
        $category = Category::factory()->create();

        $this->assertIsString($category->id);
        $this->assertEquals(36, strlen($category->id));
    }

    public function test_category_has_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Transaction::factory()
            ->count(5)
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->create();

        $this->assertCount(5, $category->transactions);
    }

    public function test_category_can_have_parent(): void
    {
        $user = User::factory()->create();
        $parentCategory = Category::factory()->for($user)->create();
        $childCategory = Category::factory()
            ->for($user)
            ->state(['parent_id' => $parentCategory->id])
            ->create();

        $this->assertInstanceOf(Category::class, $childCategory->parent);
        $this->assertEquals($parentCategory->id, $childCategory->parent->id);
    }

    public function test_category_can_have_children(): void
    {
        $user = User::factory()->create();
        $parentCategory = Category::factory()->for($user)->create();
        Category::factory()
            ->count(3)
            ->for($user)
            ->state(['parent_id' => $parentCategory->id])
            ->create();

        $this->assertCount(3, $parentCategory->children);
    }

    public function test_category_spent_this_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(500000)->create();
        $category = Category::factory()->for($user)->expense()->create();

        // Transaction this month
        Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(25000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(15000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        // Transaction last month (should not count)
        Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(100000)
            ->onDate(Carbon::now()->subMonth()->format('Y-m-d'))
            ->create();

        $category->refresh();
        $this->assertEquals(40000, $category->spent_this_month);
    }

    public function test_category_budget_progress(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(500000)->create();
        $category = Category::factory()
            ->for($user)
            ->expense()
            ->withBudget(100000)
            ->create();

        Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(50000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $category->refresh();
        $this->assertEquals(50.0, $category->budget_progress);
    }

    public function test_category_budget_progress_is_capped_at_100(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(500000)->create();
        $category = Category::factory()
            ->for($user)
            ->expense()
            ->withBudget(50000)
            ->create();

        Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(75000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $category->refresh();
        $this->assertEquals(100.0, $category->budget_progress);
    }

    public function test_category_budget_progress_is_zero_without_budget(): void
    {
        $category = Category::factory()->expense()->create(['budget_limit' => null]);

        $this->assertEquals(0, $category->budget_progress);
    }

    public function test_category_casts_is_system_to_boolean(): void
    {
        $category = Category::factory()->system()->create();

        $this->assertIsBool($category->is_system);
        $this->assertTrue($category->is_system);
    }

    public function test_category_can_be_expense_or_income_type(): void
    {
        $expenseCategory = Category::factory()->expense()->create();
        $incomeCategory = Category::factory()->income()->create();

        $this->assertEquals('expense', $expenseCategory->type);
        $this->assertEquals('income', $incomeCategory->type);
    }
}
