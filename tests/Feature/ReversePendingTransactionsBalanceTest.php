<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rattrapage P4 : les transactions en attente créées avant le correctif avaient déjà
 * modifié le solde ; la commande retire cet effet une seule fois.
 */
class ReversePendingTransactionsBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** Reproduit une transaction en attente créée par l'ancien code (déjà comptée). */
    private function legacyPendingIncome(Account $account, int $amount): Transaction
    {
        $transaction = new Transaction([
            'amount' => $amount, 'type' => 'income', 'account_id' => $account->id,
            'date' => now()->toDateString(), 'status' => Transaction::STATUS_PENDING,
        ]);
        $transaction->user_id = $account->user_id;
        $transaction->save();
        $account->adjustBalance($amount, 'income', 'transaction', $transaction->id);

        return $transaction;
    }

    public function test_it_removes_the_legacy_effect_of_pending_transactions_once(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();
        $this->legacyPendingIncome($account, 5000);
        $this->assertEquals(15000, $account->refresh()->balance);

        $this->artisan('transactions:reverse-pending-balance')->assertSuccessful();
        $this->assertEquals(10000, $account->refresh()->balance);

        $this->artisan('transactions:reverse-pending-balance')->assertSuccessful();
        $this->assertEquals(10000, $account->refresh()->balance);
    }

    public function test_validation_after_the_catch_up_counts_the_transaction_once(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();
        $transaction = $this->legacyPendingIncome($account, 5000);

        $this->artisan('transactions:reverse-pending-balance')->assertSuccessful();
        $transaction->update(['status' => 'confirmed']);

        $this->assertEquals(15000, $account->refresh()->balance);
    }

    public function test_it_ignores_pending_sms_created_after_the_fix(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();

        // Créée par le nouveau webhook : empreinte posée, solde jamais touché
        $transaction = new Transaction([
            'amount' => 5000, 'type' => 'income', 'account_id' => $account->id,
            'date' => now()->toDateString(), 'status' => Transaction::STATUS_PENDING,
        ]);
        $transaction->user_id = $account->user_id;
        $transaction->sms_fingerprint = str_repeat('a', 64);
        $transaction->save();

        $this->artisan('transactions:reverse-pending-balance')->assertSuccessful();

        $this->assertEquals(10000, $account->refresh()->balance);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();
        $this->legacyPendingIncome($account, 5000);

        $this->artisan('transactions:reverse-pending-balance', ['--dry-run' => true])->assertSuccessful();

        $this->assertEquals(15000, $account->refresh()->balance);
    }
}
