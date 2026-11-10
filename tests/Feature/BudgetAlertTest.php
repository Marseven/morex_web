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
 * Module plafonds-alertes (5b) : RM-1 à RM-5, envoi push simulé.
 */
class BudgetAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $outings;

    /** @var list<array{int, array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(1000000)->create();
        $this->outings = Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Sorties/Loisirs', 'type' => 'expense', 'budget_limit' => 170000]);

        $sent = &$this->sent;
        $this->app->instance(PushNotifier::class, new class($sent) implements PushNotifier
        {
            public function __construct(private array &$sent) {}

            public function sendToUser(User $user, array $payload): int
            {
                $this->sent[] = [$user->id, $payload];

                return 1;
            }
        });
    }

    private function spend(int $amount, ?Category $category = null, array $extra = []): Transaction
    {
        $tx = new Transaction($extra + [
            'amount' => $amount, 'type' => 'expense', 'category_id' => ($category ?? $this->outings)->id,
            'account_id' => $this->account->id, 'date' => now()->toDateString(), 'beneficiary' => 'night club',
        ]);
        $tx->user_id = $this->user->id;
        $tx->save();

        return $tx;
    }

    public function test_crossing_eighty_percent_records_and_pushes_one_alert(): void
    {
        $this->spend(120000);
        $this->assertSame(0, BudgetAlert::count());

        $this->spend(40500);

        $alert = BudgetAlert::sole();
        $this->assertSame([80, 160500, 170000], [$alert->threshold, $alert->spent, $alert->limit]);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('160 500 / 170 000', $this->sent[0][1]['body']);
        $this->assertStringContainsString('9 500', $this->sent[0][1]['body']);
    }

    public function test_the_same_threshold_is_not_alerted_twice_in_a_cycle(): void
    {
        $this->spend(140000);
        $this->spend(5000);
        $this->spend(5000);

        $this->assertSame(1, BudgetAlert::count());
        $this->assertCount(1, $this->sent);
    }

    public function test_jumping_past_one_hundred_sends_only_the_overrun_alert(): void
    {
        $this->spend(182000);

        $this->assertSame([100], BudgetAlert::pluck('threshold')->all());
        $this->assertStringContainsString('12 000', $this->sent[0][1]['body']);
    }

    public function test_both_thresholds_are_reached_in_turn(): void
    {
        $this->spend(140000);
        $this->spend(40000);

        $this->assertEqualsCanonicalizing([80, 100], BudgetAlert::pluck('threshold')->all());
        $this->assertCount(2, $this->sent);
    }

    public function test_a_new_cycle_starts_counting_again(): void
    {
        $this->spend(150000);
        $this->travelTo(now()->setDate(2026, 12, 2));

        $this->spend(150000);

        $this->assertSame(2, BudgetAlert::where('threshold', 80)->count());
    }

    public function test_the_active_budget_cycle_defines_the_period(): void
    {
        $cycle = new BudgetCycle(['start_date' => '2026-11-05', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();
        $this->spend(150000, extra: ['date' => '2026-11-03']); // avant le cycle

        $this->spend(10000);

        $this->assertSame(0, BudgetAlert::count());
    }

    public function test_pending_operations_and_uncapped_categories_never_alert(): void
    {
        $this->spend(200000, extra: ['status' => Transaction::STATUS_PENDING]);
        $free = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense', 'budget_limit' => null]);
        $this->spend(900000, $free);

        $this->assertSame(0, BudgetAlert::count());
    }

    public function test_validating_a_pending_operation_triggers_the_check(): void
    {
        $tx = $this->spend(160000, extra: ['status' => Transaction::STATUS_PENDING]);

        $tx->update(['status' => 'confirmed']);

        $this->assertSame([80], BudgetAlert::pluck('threshold')->all());
    }

    public function test_sync_pushed_expenses_are_checked_after_commit(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'transaction', 'action' => 'create', 'local_id' => 'l1',
            'data' => ['amount' => 175000, 'type' => 'expense', 'account_id' => $this->account->id, 'category_id' => $this->outings->id, 'date' => now()->toDateString()],
            'updated_at' => now()->toIso8601String(),
        ]]])->assertOk();

        $this->assertSame([100], BudgetAlert::pluck('threshold')->all());
    }

    public function test_api_lists_the_alerts_of_the_current_cycle_for_the_owner_only(): void
    {
        $this->spend(150000);
        $this->getJson('/api/budget-alerts')->assertUnauthorized();

        Sanctum::actingAs($this->user);
        $this->getJson('/api/budget-alerts')
            ->assertOk()
            ->assertJsonPath('data.0.threshold', 80)
            ->assertJsonPath('data.0.category', 'Sorties/Loisirs');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/budget-alerts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_dashboard_lists_caps_and_cycle_alerts(): void
    {
        $this->spend(150000);

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('budgetAlerts.0.threshold', 80)
                ->where('budgetCategories.0.spent', 150000)
            );
    }
}
