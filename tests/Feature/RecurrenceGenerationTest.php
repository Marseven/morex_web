<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PlannedPurchaseService;
use App\Services\RecurrenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module recurrences-serveur (6) : RM-1 à RM-5.
 */
class RecurrenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $rent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(6, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(1000000)->create();
        $this->rent = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense', 'name' => 'Loyer']);
    }

    private function recurring(array $attributes = []): RecurringTransaction
    {
        $r = new RecurringTransaction;
        $r->forceFill($attributes + [
            'user_id' => $this->user->id, 'account_id' => $this->account->id, 'category_id' => $this->rent->id,
            'type' => 'expense', 'amount' => 150000, 'beneficiary' => 'Bailleur', 'frequency' => 'monthly',
            'day_of_month' => 5, 'start_date' => '2026-01-05', 'next_due_date' => '2026-11-05', 'is_active' => true,
        ])->save();

        return $r;
    }

    private function generator(): RecurrenceGenerator
    {
        return app(RecurrenceGenerator::class);
    }

    public function test_a_due_recurrence_creates_its_operation_and_moves_on(): void
    {
        $r = $this->recurring();

        $this->assertSame(1, $this->generator()->runForUser($this->user));

        $tx = Transaction::sole();
        $this->assertSame([150000, '2026-11-05', $r->id, '2026-11-05'], [$tx->amount, $tx->date->toDateString(), $tx->recurring_transaction_id, $tx->occurrence_date->toDateString()]);
        $this->assertSame('2026-12-05', $r->fresh()->next_due_date->toDateString());
        $this->assertSame(850000, (int) $this->account->fresh()->balance);
    }

    public function test_missed_occurrences_are_caught_up(): void
    {
        $r = $this->recurring(['frequency' => 'daily', 'next_due_date' => '2026-11-08', 'day_of_month' => null]);

        $this->generator()->runForUser($this->user);

        $this->assertSame(['2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11'], Transaction::orderBy('date')->get()->map(fn ($t) => $t->date->toDateString())->all());
        $this->assertSame('2026-11-12', $r->fresh()->next_due_date->toDateString());
    }

    public function test_catch_up_is_capped_per_run(): void
    {
        $this->recurring(['frequency' => 'daily', 'next_due_date' => '2026-07-01', 'day_of_month' => null]);

        $this->generator()->runForUser($this->user);

        $this->assertSame(RecurrenceGenerator::MAX_PER_RUN, Transaction::count());
    }

    public function test_an_occurrence_is_never_generated_twice(): void
    {
        $r = $this->recurring();
        $this->generator()->runForUser($this->user);
        // La synchro iPhone remet l'ancienne échéance (édition faite hors ligne)
        $r->forceFill(['next_due_date' => '2026-11-05'])->save();

        $this->generator()->runForUser($this->user);

        $this->assertSame(1, Transaction::count());
        $this->assertSame('2026-12-05', $r->fresh()->next_due_date->toDateString());
    }

    public function test_end_date_and_occurrences_deactivate(): void
    {
        $byDate = $this->recurring(['end_date' => '2026-11-30']);
        $byCount = $this->recurring(['remaining_occurrences' => 1]);

        $this->generator()->runForUser($this->user);

        $this->assertFalse($byDate->fresh()->is_active);
        $this->assertFalse($byCount->fresh()->is_active);
        $this->assertSame(2, Transaction::count());
    }

    public function test_a_deleted_account_deactivates_without_breaking_others(): void
    {
        $other = Account::factory()->for($this->user)->withBalance(0)->create();
        $orphan = $this->recurring(['account_id' => $other->id]);
        $other->delete();
        $this->recurring();

        $this->assertSame(1, $this->generator()->runForUser($this->user));
        $this->assertFalse($orphan->fresh()->is_active);
    }

    public function test_a_foreign_account_is_never_used(): void
    {
        $stranger = Account::factory()->for(User::factory()->create())->withBalance(0)->create();
        $r = $this->recurring(['account_id' => $stranger->id]);

        $this->generator()->runForUser($this->user);

        $this->assertSame(0, Transaction::count());
        $this->assertFalse($r->fresh()->is_active);
    }

    public function test_the_scheduled_command_runs_every_user(): void
    {
        $this->recurring();

        $this->artisan('recurrences:generate')->assertSuccessful();

        $this->assertSame(1, Transaction::count());
    }

    public function test_the_api_uses_the_same_generator(): void
    {
        $r = $this->recurring();
        $this->postJson('/api/recurring-transactions-process-due')->assertUnauthorized();

        Sanctum::actingAs($this->user);
        $this->postJson('/api/recurring-transactions-process-due')->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/api/recurring-transactions-process-due')->assertOk()->assertJsonPath('count', 0);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/recurring-transactions/{$r->id}/generate")->assertForbidden();
    }

    public function test_recurring_expenses_are_never_out_of_list(): void
    {
        $this->recurring(['amount' => 150000]);
        $this->generator()->runForUser($this->user);

        $this->assertCount(0, app(PlannedPurchaseService::class)->outOfList($this->user));
        $this->assertFalse(app(PlannedPurchaseService::class)->isSubjectToRule(Transaction::sole()));
    }

    public function test_the_sync_pull_carries_the_recurrence_link(): void
    {
        $r = $this->recurring();
        $this->generator()->runForUser($this->user);
        Sanctum::actingAs($this->user);

        $this->getJson('/api/sync/pull')->assertOk()
            ->assertJsonPath('transactions.0.recurring_transaction_id', $r->id);
    }

    // --- Revue de code ---

    /** @return list<string> échéances générées, dans l'ordre */
    private function occurrences(): array
    {
        return Transaction::orderBy('occurrence_date')->get()->map(fn ($t) => $t->occurrence_date->toDateString())->all();
    }

    public function test_month_ends_never_skip_february(): void
    {
        $this->travelTo(now()->setDate(2027, 4, 1));
        $this->recurring(['day_of_month' => 31, 'start_date' => '2027-01-31', 'next_due_date' => '2027-01-31']);

        $this->generator()->runForUser($this->user);

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $this->occurrences());
    }

    public function test_without_day_of_month_the_start_day_is_kept(): void
    {
        $this->travelTo(now()->setDate(2027, 4, 1));
        $this->recurring(['day_of_month' => null, 'start_date' => '2027-01-31', 'next_due_date' => '2027-01-31']);

        $this->generator()->runForUser($this->user);

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $this->occurrences());
    }

    public function test_quarterly_and_yearly_never_overflow(): void
    {
        $this->travelTo(now()->setDate(2029, 3, 1));
        $this->recurring(['frequency' => 'quarterly', 'day_of_month' => null, 'start_date' => '2028-11-30', 'next_due_date' => '2028-11-30']);
        $this->recurring(['frequency' => 'yearly', 'day_of_month' => null, 'start_date' => '2028-02-29', 'next_due_date' => '2028-02-29', 'amount' => 1000]);

        $this->generator()->runForUser($this->user);

        $this->assertSame(['2028-02-29', '2028-11-30', '2029-02-28', '2029-02-28'], $this->occurrences());
    }

    public function test_one_failing_recurrence_never_stops_the_others(): void
    {
        $this->recurring(['amount' => 666]);
        $this->recurring();
        Transaction::creating(function (Transaction $t) {
            if ($t->amount === 666) {
                throw new \RuntimeException('listener en échec');
            }
        });

        $this->artisan('recurrences:generate')->assertSuccessful();

        $this->assertSame([150000], Transaction::pluck('amount')->all());
    }

    public function test_a_deleted_category_deactivates_the_recurrence(): void
    {
        $r = $this->recurring();
        $this->rent->delete();

        $this->assertSame(0, $this->generator()->runForUser($this->user));
        $this->assertFalse($r->fresh()->is_active);
    }

    public function test_generation_moves_the_balance_with_its_history(): void
    {
        $this->recurring();

        $this->generator()->runForUser($this->user);

        $this->assertDatabaseHas('balance_history', ['account_id' => $this->account->id, 'balance_after' => 850000]);
    }

    public function test_the_sync_cannot_rewind_a_recurrence_to_a_generated_occurrence(): void
    {
        $r = $this->recurring();
        $this->generator()->runForUser($this->user);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'recurring_transaction', 'action' => 'update', 'server_id' => $r->id, 'local_id' => 'r1',
            'data' => ['next_due_date' => '2026-11-05', 'last_generated_date' => '2026-10-05', 'amount' => 160000],
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();

        $r->refresh();
        $this->assertSame(['2026-12-05', '2026-11-05', 160000], [$r->next_due_date->toDateString(), $r->last_generated_date->toDateString(), $r->amount]);
    }

    public function test_generate_endpoint_returns_the_created_operation(): void
    {
        $r = $this->recurring();
        Sanctum::actingAs($this->user);

        $id = $this->postJson("/api/recurring-transactions/{$r->id}/generate")->assertOk()->json('transaction.id');

        $this->assertSame(Transaction::sole()->id, $id);
    }
}
