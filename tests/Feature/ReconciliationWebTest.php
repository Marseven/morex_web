<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Module rapprochement : écran web (design A) et badge de la page Comptes.
 */
class ReconciliationWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(100000)->create();
    }

    public function test_the_page_shows_balance_and_transactions_to_check(): void
    {
        $open = Transaction::factory()->for($this->user)->create([
            'account_id' => $this->account->id, 'type' => 'expense', 'amount' => 1500,
            'date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get("/accounts/{$this->account->id}/reconcile")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Reconcile')
                ->where('account.id', $this->account->id)
                ->where('account.balance', 98500)
                ->where('uncleared.0.id', $open->id)
                ->where('uncleared.0.signed_amount', -1500)
                ->where('lastReconciliation', null)
            );
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get("/accounts/{$this->account->id}/reconcile")->assertRedirect('/login');
    }

    public function test_someone_else_account_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get("/accounts/{$this->account->id}/reconcile")
            ->assertForbidden();
    }

    public function test_validating_records_the_reconciliation_and_returns_to_accounts(): void
    {
        $this->actingAs($this->user)
            ->post("/accounts/{$this->account->id}/reconciliations", ['statement_balance' => 102300])
            ->assertRedirect('/accounts')
            ->assertSessionHas('success');

        $this->assertSame(2300, Reconciliation::sole()->difference);
        $this->assertSame(102300, $this->account->refresh()->balance);
    }

    public function test_an_invalid_balance_comes_back_with_an_error(): void
    {
        $this->actingAs($this->user)
            ->from("/accounts/{$this->account->id}/reconcile")
            ->post("/accounts/{$this->account->id}/reconciliations", ['statement_balance' => '12,5'])
            ->assertRedirect("/accounts/{$this->account->id}/reconcile")
            ->assertSessionHasErrors('statement_balance');
    }

    public function test_accounts_page_exposes_the_last_reconciliation_date(): void
    {
        $this->actingAs($this->user)->post("/accounts/{$this->account->id}/reconciliations", ['statement_balance' => 100000]);

        $this->actingAs($this->user)
            ->get('/accounts')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Index')
                ->whereNot('accounts.0.last_reconciled_at', null)
            );
    }

    public function test_posting_on_someone_else_account_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/accounts/{$this->account->id}/reconciliations", ['statement_balance' => 1])
            ->assertForbidden();

        $this->assertSame(0, Reconciliation::count());
        $this->assertSame(100000, $this->account->refresh()->balance);
    }

    public function test_manual_adjust_rejects_out_of_range_amounts(): void
    {
        $this->actingAs($this->user)
            ->post("/accounts/{$this->account->id}/adjust-balance", ['balance' => PHP_INT_MAX])
            ->assertSessionHasErrors('balance');

        $this->assertSame(100000, $this->account->refresh()->balance);
    }
}
