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
}
