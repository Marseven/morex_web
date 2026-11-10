<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module rapprochement : API (auth, autorisation, validation, IDOR).
 */
class ReconciliationApiTest extends TestCase
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

    private function expense(int $amount, ?Account $account = null): Transaction
    {
        $account ??= $this->account;

        return Transaction::factory()->for($account->user)->create([
            'account_id' => $account->id, 'type' => 'expense', 'amount' => $amount,
            'date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson("/api/accounts/{$this->account->id}/reconciliation")->assertUnauthorized();
        $this->postJson("/api/accounts/{$this->account->id}/reconciliations")->assertUnauthorized();
    }

    public function test_preview_lists_balance_and_transactions_to_check(): void
    {
        $open = $this->expense(1500);
        Sanctum::actingAs($this->user);

        $this->getJson("/api/accounts/{$this->account->id}/reconciliation")
            ->assertOk()
            ->assertJsonPath('data.balance', 98500)
            ->assertJsonPath('data.last_reconciliation', null)
            ->assertJsonPath('data.uncleared.0.id', $open->id)
            ->assertJsonPath('data.uncleared.0.signed_amount', -1500);
    }

    public function test_someone_else_account_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/accounts/{$this->account->id}/reconciliation")->assertForbidden();
        $this->postJson("/api/accounts/{$this->account->id}/reconciliations", ['statement_balance' => 1])->assertForbidden();
        $this->assertDatabaseCount('reconciliations', 0);
    }

    public function test_store_records_the_reconciliation_with_its_gap(): void
    {
        $open = $this->expense(1500);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/accounts/{$this->account->id}/reconciliations", [
            'statement_balance' => 100800,
            'cleared_ids' => [$open->id],
            'note' => 'Relevé Airtel',
        ])->assertCreated()
            ->assertJsonPath('data.difference', 2300)
            ->assertJsonPath('data.balance_before', 98500)
            ->assertJsonPath('data.account_balance', 100800);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'solde manquant' => [[]],
            'solde décimal' => [['statement_balance' => 100.5]],
            'solde texte' => [['statement_balance' => 'beaucoup']],
            'id non uuid' => [['statement_balance' => 1, 'cleared_ids' => ['abc']]],
            'note trop longue' => [['statement_balance' => 1, 'note' => str_repeat('x', 501)]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function test_invalid_payloads_get_422(array $payload): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/accounts/{$this->account->id}/reconciliations", $payload)->assertUnprocessable();
        $this->assertDatabaseCount('reconciliations', 0);
    }

    public function test_a_transaction_from_another_users_account_gets_422(): void
    {
        $foreign = $this->expense(500, Account::factory()->for(User::factory())->create());
        Sanctum::actingAs($this->user);

        $this->postJson("/api/accounts/{$this->account->id}/reconciliations", [
            'statement_balance' => 100000,
            'cleared_ids' => [$foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('cleared_ids');

        $this->assertNull($foreign->refresh()->reconciled_at);
    }

    public function test_the_unsafe_balance_reset_endpoint_is_gone(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/accounts/balance-reset', [
            'accounts' => [['id' => $this->account->id, 'real_balance' => 1]],
        ])->assertMethodNotAllowed(); // seule reste la route /accounts/{account} (GET, PUT, DELETE)

        $this->assertSame(100000, $this->account->refresh()->balance);
    }
}
