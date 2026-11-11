<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module dette-qualite (7a) : un test de non-régression par point de dette.
 */
class QualityDebtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $food;

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
        $this->account = Account::factory()->for($this->user)->withBalance(500000)->create(['name' => 'Compte Courant']);
        $this->food = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense', 'name' => 'Alimentation']);
    }

    private function spend(int $amount, array $extra = []): Transaction
    {
        $tx = new Transaction($extra + ['amount' => $amount, 'type' => 'expense', 'category_id' => $this->food->id, 'account_id' => $this->account->id, 'date' => now()->toDateString()]);
        $tx->user_id = $this->user->id;
        $tx->save();

        return $tx;
    }

    private function reconciled(int $amount = 10000): Transaction
    {
        $tx = $this->spend($amount);
        $tx->forceFill(['reconciled_at' => now()])->save();

        return $tx->fresh();
    }

    // 1. Opérations pointées

    public function test_editing_a_reconciled_operation_unpoints_it_on_the_web(): void
    {
        $tx = $this->reconciled();

        $this->actingAs($this->user)->put("/transactions/{$tx->id}", [
            'amount' => 12000, 'type' => 'expense', 'category_id' => $this->food->id,
            'account_id' => $this->account->id, 'date' => now()->toDateString(),
        ])->assertSessionHas('warning');

        $this->assertNull($tx->fresh()->reconciled_at);
    }

    public function test_a_cosmetic_edit_keeps_the_operation_pointed(): void
    {
        $tx = $this->reconciled();

        $tx->update(['description' => 'Marché']);

        $this->assertNotNull($tx->fresh()->reconciled_at);
    }

    public function test_the_sync_unpoints_a_reconciled_operation_too(): void
    {
        $tx = $this->reconciled();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'transaction', 'action' => 'update', 'server_id' => $tx->id, 'local_id' => 't1',
            'data' => ['amount' => 15000], 'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();

        $this->assertNull($tx->fresh()->reconciled_at);
    }

    // 2. Catégories d'écart

    public function test_reconciliation_categories_are_refused_on_the_web_and_api(): void
    {
        $payload = ['amount' => 5000, 'type' => 'expense', 'category_id' => Category::RECONCILIATION_EXPENSE_ID,
            'account_id' => $this->account->id, 'date' => now()->toDateString()];

        $this->actingAs($this->user)->post('/transactions', $payload)->assertSessionHasErrors('category_id');
        Sanctum::actingAs($this->user);
        $this->postJson('/api/transactions', $payload)->assertUnprocessable();
    }

    public function test_the_sync_refuses_a_reconciliation_category_on_a_normal_operation(): void
    {
        $tx = $this->spend(5000);
        Sanctum::actingAs($this->user);

        $result = $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'transaction', 'action' => 'update', 'server_id' => $tx->id, 'local_id' => 't1',
            'data' => ['category_id' => Category::RECONCILIATION_EXPENSE_ID], 'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk()->json('results.errors.0.status');

        $this->assertSame('error', $result);
        $this->assertSame($this->food->id, $tx->fresh()->category_id);
    }

    public function test_an_existing_adjustment_keeps_its_category(): void
    {
        $adjustment = $this->spend(3000, ['category_id' => Category::RECONCILIATION_EXPENSE_ID]);
        Sanctum::actingAs($this->user);

        $this->putJson("/api/transactions/{$adjustment->id}", [
            'amount' => 3000, 'type' => 'expense', 'category_id' => Category::RECONCILIATION_EXPENSE_ID,
            'account_id' => $this->account->id, 'date' => now()->toDateString(), 'description' => 'Écart corrigé',
        ])->assertOk();
    }

    // 3. P12 : changement de date

    public function test_moving_an_operation_recomputes_both_cycles(): void
    {
        $old = new BudgetCycle(['start_date' => '2026-09-25', 'end_date' => '2026-10-24', 'period_name' => 'Oct', 'status' => 'closed']);
        $old->user_id = $this->user->id;
        $old->save();
        $tx = $this->spend(40000, ['date' => '2026-10-10']);
        $old->refreshSpent();
        $this->assertSame(40000, $old->fresh()->total_spent);
        $new = new BudgetCycle(['start_date' => '2026-10-25', 'period_name' => 'Nov', 'status' => 'active']);
        $new->user_id = $this->user->id;
        $new->save();

        $tx->update(['date' => '2026-11-02']);

        $this->assertSame([0, 40000], [$old->fresh()->total_spent, $new->fresh()->total_spent]);
    }

    // 4. P13 : webhook sans source

    public function test_an_sms_without_source_never_matches_the_first_account(): void
    {
        Account::factory()->for($this->user)->withBalance(0)->create(['name' => 'Airtel Money']);
        $match = fn (?string $source) => \App\Http\Controllers\Api\WebhookController::accountIdForSource($this->user, $source);

        $this->assertNull($match(null));
        $this->assertNull($match('  '));
        $this->assertNull($match('%')); // un joker n'attrape plus le premier compte venu
        $this->assertSame($this->user->accounts()->where('name', 'Airtel Money')->value('id'), $match('AIRTEL'));
    }

    // 5. P16 : export CSV

    public function test_the_csv_export_neutralises_formulas(): void
    {
        $this->spend(1000, ['beneficiary' => '=HYPERLINK("http://x")', 'description' => '@SUM(A1)']);

        $csv = $this->actingAs($this->user)->get('/transactions/export')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
    }

    // 6. Dépensé par catégorie

    public function test_spent_per_category_ignores_pending_operations(): void
    {
        $this->spend(20000);
        $this->spend(90000, ['status' => Transaction::STATUS_PENDING]);

        $this->assertSame(20000, $this->food->fresh()->spent_this_month);
        $this->actingAs($this->user)->get('/budgets')
            ->assertInertia(fn ($page) => $page->where('categories', fn ($cats) => collect($cats)->firstWhere('id', $this->food->id)['spent_this_month'] === 20000));
    }

    // 7. Saisie web

    public function test_the_web_entry_validates_through_its_form_request(): void
    {
        $this->actingAs($this->user)->post('/transactions', ['amount' => 0, 'type' => 'loan', 'account_id' => $this->account->id, 'date' => 'hier'])
            ->assertSessionHasErrors(['amount', 'type', 'date']);
        $this->actingAs($this->user)->post('/transactions', ['amount' => 5000, 'type' => 'expense', 'account_id' => $this->account->id, 'date' => now()->toDateString()])
            ->assertRedirect('/transactions');
        $this->assertSame(1, Transaction::count());
    }
}
