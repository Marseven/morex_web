<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Non-régression P4 : une transaction SMS en attente de validation ne touche ni le solde
 * ni le cycle tant qu'elle n'est pas validée ; un même SMS reçu deux fois ne crée qu'une ligne.
 */
class SmsPendingTransactionTest extends TestCase
{
    use RefreshDatabase;

    private const SMS = 'Votre compte a ete credite de 5000 FCFA. De: John';

    private function postSms(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/webhook/sms', ['message' => self::SMS, 'sender' => 'Airtel']);
    }

    private function userWithAccount(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(10000)->create(['is_default' => true]);

        return [$user, $account];
    }

    public function test_pending_sms_transaction_leaves_the_balance_untouched(): void
    {
        [$user, $account] = $this->userWithAccount();
        Sanctum::actingAs($user, ['*']); // jeton complet, comme celui de l'app

        $this->postSms()->assertCreated();

        $this->assertEquals(10000, $account->refresh()->balance);
    }

    public function test_validating_a_pending_transaction_applies_it_once(): void
    {
        [$user, $account] = $this->userWithAccount();
        Sanctum::actingAs($user, ['*']); // jeton complet, comme celui de l'app
        $transactionId = $this->postSms()->json('transaction_id');

        $this->actingAs($user)->post("/transactions/{$transactionId}/validate");

        $this->assertEquals(15000, $account->refresh()->balance);
    }

    public function test_deleting_a_pending_transaction_leaves_the_balance_untouched(): void
    {
        [$user, $account] = $this->userWithAccount();
        Sanctum::actingAs($user, ['*']); // jeton complet, comme celui de l'app
        $transactionId = $this->postSms()->json('transaction_id');

        Transaction::find($transactionId)->delete();

        $this->assertEquals(10000, $account->refresh()->balance);
    }

    public function test_pending_transactions_are_excluded_from_cycle_totals(): void
    {
        [$user, $account] = $this->userWithAccount();
        $cycle = new BudgetCycle([
            'start_date' => now()->subDays(3)->toDateString(),
            'period_name' => 'Test',
            'status' => 'active',
        ]);
        $cycle->user_id = $user->id;
        $cycle->save();

        $pending = new Transaction([
            'amount' => 2000, 'type' => 'expense', 'account_id' => $account->id,
            'date' => now()->toDateString(), 'status' => 'pending_validation',
        ]);
        $pending->user_id = $user->id;
        $pending->save();

        $this->assertEquals(0, $cycle->refresh()->calculateTotalSpent());
    }

    public function test_the_same_sms_received_twice_creates_a_single_transaction(): void
    {
        [$user] = $this->userWithAccount();
        Sanctum::actingAs($user, ['*']); // jeton complet, comme celui de l'app

        $first = $this->postSms()->assertCreated();
        $second = $this->postSms()->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame($first->json('transaction_id'), $second->json('transaction_id'));
        $this->assertDatabaseCount('transactions', 1);
    }
}
