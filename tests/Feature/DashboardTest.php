<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_dashboard_shows_total_balance(): void
    {
        Account::factory()->for($this->user)->withBalance(100000)->create();
        Account::factory()->for($this->user)->withBalance(200000)->create();

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('totalBalance', 300000)
        );
    }

    public function test_dashboard_shows_period_income_and_expense(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $incomeCategory = Category::factory()->for($this->user)->income()->create();
        $expenseCategory = Category::factory()->for($this->user)->expense()->create();

        // Income this month
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($incomeCategory)
            ->income()
            ->withAmount(150000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        // Expense this month
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($expenseCategory)
            ->expense()
            ->withAmount(50000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('incomeForPeriod', fn ($value) => (int) $value === 150000)
            ->where('expenseForPeriod', fn ($value) => (int) $value === 50000)
            ->where('periodVariation', fn ($value) => (int) $value === 100000)
        );
    }

    public function test_dashboard_filters_by_period(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $incomeCategory = Category::factory()->for($this->user)->income()->create();

        // Transaction today
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($incomeCategory)
            ->income()
            ->withAmount(50000)
            ->onDate(Carbon::today()->format('Y-m-d'))
            ->create();

        // Transaction last week
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($incomeCategory)
            ->income()
            ->withAmount(100000)
            ->onDate(Carbon::now()->subDays(10)->format('Y-m-d'))
            ->create();

        // Test day period
        $response = $this->actingAs($this->user)->get('/?period=day');

        $response->assertInertia(fn ($page) => $page
            ->where('incomeForPeriod', fn ($value) => (int) $value === 50000)
            ->where('currentPeriod', 'day')
            ->where('periodLabel', "Aujourd'hui")
        );
    }

    public function test_dashboard_shows_recent_transactions(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category = Category::factory()->for($this->user)->create();

        Transaction::factory()
            ->count(7)
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create();

        $response = $this->actingAs($this->user)->get('/');

        // Should only show 5 recent transactions
        $response->assertInertia(fn ($page) => $page
            ->has('recentTransactions', 5)
        );
    }

    public function test_dashboard_shows_active_goals(): void
    {
        Goal::factory()->count(5)->for($this->user)->active()->create();
        Goal::factory()->for($this->user)->completed()->create();

        $response = $this->actingAs($this->user)->get('/');

        // Should only show 3 active goals
        $response->assertInertia(fn ($page) => $page
            ->has('activeGoals', 3)
        );
    }

    public function test_dashboard_shows_active_debts(): void
    {
        Debt::factory()->count(5)->for($this->user)->debt()->create();
        Debt::factory()->for($this->user)->paid()->create();

        $response = $this->actingAs($this->user)->get('/');

        // Should only show 3 active debts
        $response->assertInertia(fn ($page) => $page
            ->has('activeDebts', 3)
        );
    }

    public function test_dashboard_shows_budget_categories(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category = Category::factory()->for($this->user)->expense()->withBudget(100000)->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(35000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('budgetCategories')
            ->has('budgetCategories.0.budget_limit')
            ->has('budgetCategories.0.spent')
            ->has('budgetCategories.0.progress')
        );
    }

    public function test_dashboard_does_not_show_other_users_data(): void
    {
        $otherUser = User::factory()->create();

        Account::factory()->for($otherUser)->withBalance(1000000)->create();
        Account::factory()->for($this->user)->withBalance(100000)->create();

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('totalBalance', 100000)
        );
    }

    public function test_dashboard_shows_accounts(): void
    {
        Account::factory()->count(3)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('accounts', 3)
        );
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_period_week_filter(): void
    {
        $response = $this->actingAs($this->user)->get('/?period=week');

        $response->assertInertia(fn ($page) => $page
            ->where('currentPeriod', 'week')
            ->where('periodLabel', 'Cette semaine')
        );
    }

    public function test_dashboard_period_year_filter(): void
    {
        $response = $this->actingAs($this->user)->get('/?period=year');

        $response->assertInertia(fn ($page) => $page
            ->where('currentPeriod', 'year')
            ->where('periodLabel', 'Cette année')
        );
    }
}
