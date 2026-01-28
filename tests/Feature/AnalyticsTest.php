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

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_analytics_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Analytics/Index'));
    }

    public function test_analytics_shows_monthly_data(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('monthlyData', 12)
            ->has('monthlyData.0.month')
            ->has('monthlyData.0.income')
            ->has('monthlyData.0.expense')
            ->has('monthlyData.0.balance')
        );
    }

    public function test_analytics_calculates_monthly_income_and_expense(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $incomeCategory = Category::factory()->for($this->user)->income()->create();
        $expenseCategory = Category::factory()->for($this->user)->expense()->create();

        // January transaction
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($incomeCategory)
            ->income()
            ->withAmount(200000)
            ->onDate(Carbon::create(now()->year, 1, 15)->format('Y-m-d'))
            ->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($expenseCategory)
            ->expense()
            ->withAmount(80000)
            ->onDate(Carbon::create(now()->year, 1, 20)->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->where('monthlyData.0.income', fn ($value) => (int) $value === 200000)
            ->where('monthlyData.0.expense', fn ($value) => (int) $value === 80000)
            ->where('monthlyData.0.balance', fn ($value) => (int) $value === 120000)
        );
    }

    public function test_analytics_shows_category_breakdown(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category1 = Category::factory()->for($this->user)->expense()->create(['name' => 'Food']);
        $category2 = Category::factory()->for($this->user)->expense()->create(['name' => 'Transport']);

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category1)
            ->expense()
            ->withAmount(30000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category2)
            ->expense()
            ->withAmount(20000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('categoryBreakdown')
            ->has('categoryBreakdown.0.name')
            ->has('categoryBreakdown.0.amount')
            ->has('categoryBreakdown.0.percentage')
        );
    }

    public function test_analytics_shows_goals_progress(): void
    {
        Goal::factory()->for($this->user)->active()->create([
            'name' => 'Emergency Fund',
            'target_amount' => 1000000,
            'current_amount' => 250000,
        ]);

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('goalsProgress', 1)
            ->has('goalsProgress.0.name')
            ->has('goalsProgress.0.target')
            ->has('goalsProgress.0.current')
            ->has('goalsProgress.0.progress')
        );
    }

    public function test_analytics_shows_budget_comparison(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category = Category::factory()->for($this->user)->expense()->withBudget(100000)->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(75000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('budgetComparison')
            ->has('budgetComparison.0.budget')
            ->has('budgetComparison.0.spent')
            ->has('budgetComparison.0.remaining')
            ->has('budgetComparison.0.percentage')
            ->has('budgetComparison.0.isOverBudget')
        );
    }

    public function test_analytics_shows_debt_summary(): void
    {
        Debt::factory()->for($this->user)->debt()->withAmount(100000)->create();
        Debt::factory()->for($this->user)->credit()->withAmount(50000)->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('debtSummary')
            ->has('debtSummary.totalDebt')
            ->has('debtSummary.totalCredit')
            ->has('debtSummary.debtCount')
            ->has('debtSummary.creditCount')
            ->has('debtSummary.netPosition')
            ->has('debtSummary.overdueCount')
        );
    }

    public function test_analytics_calculates_savings_rate(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $incomeCategory = Category::factory()->for($this->user)->income()->create();
        $expenseCategory = Category::factory()->for($this->user)->expense()->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($incomeCategory)
            ->income()
            ->withAmount(1000000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($expenseCategory)
            ->expense()
            ->withAmount(750000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('savingsRate')
            ->where('savingsRate.income', fn ($value) => (int) $value === 1000000)
            ->where('savingsRate.expense', fn ($value) => (int) $value === 750000)
            ->where('savingsRate.savings', fn ($value) => (int) $value === 250000)
            ->where('savingsRate.rate', fn ($value) => (float) $value === 25.0)
            ->where('savingsRate.target', 25)
        );
    }

    public function test_analytics_can_filter_by_year(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics?year=' . (now()->year - 1));

        $response->assertInertia(fn ($page) => $page
            ->where('currentYear', fn ($value) => (int) $value === now()->year - 1)
        );
    }

    public function test_analytics_can_filter_by_period(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics?period=week');

        $response->assertInertia(fn ($page) => $page
            ->where('currentPeriod', 'week')
        );
    }

    public function test_analytics_shows_available_years(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('availableYears')
        );
    }

    public function test_analytics_export_csv(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category = Category::factory()->for($this->user)->expense()->create();

        Transaction::factory()
            ->count(3)
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->expense()
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics/export?format=csv');

        $response->assertStatus(200);
        $response->assertDownload();
    }

    public function test_analytics_export_unsupported_format_returns_error(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics/export?format=pdf');

        $response->assertRedirect();
    }

    public function test_analytics_does_not_show_other_users_data(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->withBalance(500000)->create();
        $otherCategory = Category::factory()->for($otherUser)->income()->create();

        Transaction::factory()
            ->for($otherUser)
            ->for($otherAccount)
            ->for($otherCategory)
            ->income()
            ->withAmount(1000000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        // User should see 0 income since they have no transactions
        $response->assertInertia(fn ($page) => $page
            ->where('savingsRate.income', fn ($value) => (int) $value === 0)
        );
    }

    public function test_analytics_requires_authentication(): void
    {
        $response = $this->get('/analytics');

        $response->assertRedirect('/login');
    }

    public function test_analytics_debt_summary_calculates_overdue_count(): void
    {
        // Active overdue debt
        Debt::factory()->for($this->user)->overdue()->debt()->create();

        // Active not overdue debt
        Debt::factory()->for($this->user)->debt()->create([
            'due_date' => Carbon::now()->addDays(10),
            'status' => 'active',
        ]);

        // Paid debt (should not count)
        Debt::factory()->for($this->user)->paid()->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->where('debtSummary.overdueCount', 1)
        );
    }

    public function test_analytics_only_shows_active_goals_progress(): void
    {
        Goal::factory()->for($this->user)->active()->create();
        Goal::factory()->for($this->user)->completed()->create();

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertInertia(fn ($page) => $page
            ->has('goalsProgress', 1)
        );
    }
}
