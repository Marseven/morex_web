<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\BudgetAlert;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module revenus-transit (5c) : nature des catégories, RM-1 à RM-5.
 */
class TransitIncomeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $food;

    private Category $salary;

    private Category $tontine;

    private Category $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });

        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(2000000)->create();
        $this->food = $this->category('Alimentation', 'expense');
        $this->salary = $this->category('Salaire', 'income');
        $this->tontine = $this->category('Tontine', 'expense', 'transit');
        $this->tools = $this->category('Outils MRTECH', 'expense', 'pro');
    }

    private function category(string $name, string $type, string $nature = 'regular', ?int $limit = null): Category
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'name' => $name, 'type' => $type, 'budget_limit' => $limit]);
        $category->forceFill(['nature' => $nature])->save();

        return $category;
    }

    private function add(string $type, int $amount, Category $category, array $extra = []): Transaction
    {
        $tx = new Transaction($extra + [
            'amount' => $amount, 'type' => $type, 'category_id' => $category->id,
            'account_id' => $this->account->id, 'date' => now()->toDateString(),
        ]);
        $tx->user_id = $this->user->id;
        $tx->save();

        return $tx;
    }

    /** Octobre type : salaire, courses, outils pro, cotisation et levée de tontine. */
    private function month(): void
    {
        $this->add('income', 800000, $this->salary);
        $this->add('expense', 100000, $this->food);
        $this->add('expense', 62000, $this->tools);
        $this->add('expense', 50000, $this->tontine);
        $this->add('income', 500000, $this->tontine);
    }

    public function test_new_categories_are_regular_by_default(): void
    {
        $this->assertSame('regular', Category::factory()->create()->fresh()->nature);
    }

    public function test_counted_scope_drops_transit_pending_and_reconciliation(): void
    {
        $this->month();
        $this->add('expense', 9000, $this->food, ['status' => Transaction::STATUS_PENDING]);

        $counted = Transaction::where('user_id', $this->user->id)->counted();

        $this->assertSame(800000, (int) (clone $counted)->where('type', 'income')->sum('amount'));
        $this->assertSame(162000, (int) (clone $counted)->where('type', 'expense')->sum('amount'));
    }

    public function test_balances_still_move_with_transit_operations(): void
    {
        $this->month();

        $this->assertSame(2000000 + 800000 - 100000 - 62000 - 50000 + 500000, (int) $this->account->fresh()->balance);
    }

    public function test_the_cycle_compares_personal_spending_with_personal_caps(): void
    {
        $this->food->update(['budget_limit' => 120000]);
        $this->tools->update(['budget_limit' => 90000]);
        $this->tontine->update(['budget_limit' => 50000]);
        $cycle = new BudgetCycle(['start_date' => '2026-11-01', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();

        $this->month();
        $cycle->refresh()->updateTotals();

        $this->assertSame(800000, $cycle->calculateTotalIncome());
        $this->assertSame(100000, $cycle->total_spent);
        $this->assertSame(120000, $cycle->total_budget);
    }

    public function test_the_dashboard_counts_pro_but_isolates_transit(): void
    {
        $this->month();

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('incomeForPeriod', 800000)
                ->where('expenseForPeriod', 162000)
                ->where('proExpense', 62000)
                ->where('transit.out', 50000)
                ->where('transit.in', 500000)
                ->where('transit.net', 450000)
            );
    }

    public function test_the_dashboard_hides_transit_without_transit_categories(): void
    {
        $this->tontine->forceFill(['nature' => 'regular'])->save();
        $this->tools->forceFill(['nature' => 'regular'])->save();

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn ($page) => $page->where('transit', null)->where('proExpense', null));
    }

    public function test_api_stats_and_analytics_ignore_transit(): void
    {
        $this->month();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/stats/dashboard')
            ->assertOk()
            ->assertJsonPath('monthly.income', 800000)
            ->assertJsonPath('monthly.expense', 162000);

        $this->actingAs($this->user)->get('/analytics')
            ->assertInertia(fn ($page) => $page
                ->where('monthlyData.10.income', 800000)
                ->where('monthlyData.10.expense', 162000)
            );
    }

    public function test_a_capped_transit_category_never_alerts(): void
    {
        $this->tontine->update(['budget_limit' => 10000]);

        $this->add('expense', 50000, $this->tontine);

        $this->assertSame(0, BudgetAlert::count());
    }

    public function test_the_nature_is_set_from_the_web_form(): void
    {
        $this->actingAs($this->user)
            ->post('/budgets', ['name' => 'Avances', 'type' => 'expense', 'nature' => 'transit'])
            ->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Avances', 'nature' => 'transit']);

        $this->actingAs($this->user)
            ->put('/budgets/'.$this->food->id, ['name' => 'Alimentation', 'type' => 'expense', 'nature' => 'pro'])
            ->assertRedirect();
        $this->assertSame('pro', $this->food->fresh()->nature);

        $this->actingAs($this->user)
            ->post('/budgets', ['name' => 'X', 'type' => 'expense', 'nature' => 'loan'])
            ->assertSessionHasErrors('nature');
    }

    public function test_the_nature_is_exposed_and_validated_by_the_api(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/categories', ['name' => 'Avances', 'type' => 'expense', 'nature' => 'transit'])
            ->assertCreated()
            ->assertJsonPath('nature', 'transit');
        $this->postJson('/api/categories', ['name' => 'X', 'type' => 'expense', 'nature' => 'loan'])
            ->assertUnprocessable();

        $system = Category::factory()->create(['user_id' => null, 'is_system' => true]);
        $this->putJson('/api/categories/'.$system->id, ['name' => 'X', 'type' => 'expense', 'nature' => 'transit'])
            ->assertForbidden();
    }

    public function test_transactions_can_be_filtered_on_transit(): void
    {
        $this->month();

        $this->actingAs($this->user)->get('/transactions?nature=transit')
            ->assertInertia(fn ($page) => $page->has('transactions.data', 2));
    }

    // --- Revue de code ---

    public function test_every_stats_endpoint_agrees_on_transit(): void
    {
        $this->month();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/transactions-stats')
            ->assertOk()
            ->assertJsonPath('income', 800000)
            ->assertJsonPath('expense', 162000);

        $monthly = $this->getJson('/api/stats/monthly?month=2026-11')->assertOk()->json();
        $this->assertSame(800000, (int) collect($monthly['daily'])->sum('income'));
        $this->assertSame(162000, (int) collect($monthly['daily'])->sum('expense'));
        $this->assertSame(162000, (int) collect($monthly['by_account'])->sum('expense'));
    }

    public function test_a_new_cycle_starts_with_the_personal_budget_only(): void
    {
        $this->food->update(['budget_limit' => 120000]);
        $this->tools->update(['budget_limit' => 90000]);
        $this->tontine->update(['budget_limit' => 50000]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/budget-cycles/start', ['start_date' => '2026-11-11'])->assertSuccessful();

        $this->assertSame(120000, (int) BudgetCycle::where('user_id', $this->user->id)->where('status', 'active')->value('total_budget'));
    }

    public function test_a_closure_saves_income_minus_all_counted_expenses(): void
    {
        $cycle = new BudgetCycle(['start_date' => '2026-11-01', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();
        $this->month();

        $cycle->refresh()->close(now()->addDays(2));
        $closure = $cycle->createClosure();

        $this->assertSame([800000, 100000, 638000], [(int) $closure->total_income, (int) $closure->total_spent, (int) $closure->total_saved]);
    }

    public function test_uncategorized_operations_stay_counted(): void
    {
        $tx = new Transaction(['amount' => 7000, 'type' => 'expense', 'account_id' => $this->account->id, 'date' => now()->toDateString()]);
        $tx->user_id = $this->user->id;
        $tx->save();

        $this->assertSame(7000, (int) Transaction::where('user_id', $this->user->id)->counted()->sum('amount'));
    }

    public function test_a_deleted_transit_category_keeps_its_operations_excluded(): void
    {
        $this->add('income', 500000, $this->tontine);
        $this->tontine->delete();

        $this->assertSame(0, (int) Transaction::where('user_id', $this->user->id)->counted()->sum('amount'));
    }
}
