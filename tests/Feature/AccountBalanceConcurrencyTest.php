<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Non-régression P3 : adjustBalance part du solde en base, pas de la copie en mémoire.
 * Deux requêtes qui ont chargé le compte au même état ne doivent pas perdre un mouvement.
 */
class AccountBalanceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_stale_instances_do_not_lose_a_movement(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();

        // Deux requêtes concurrentes chargent le même état (solde 10 000)
        $requestA = Account::find($account->id);
        $requestB = Account::find($account->id);

        $requestA->adjustBalance(-1000, 'expense');
        $requestB->adjustBalance(-500, 'expense');

        $this->assertEquals(8500, $account->refresh()->balance);
    }

    public function test_history_records_the_real_balance_before_and_after(): void
    {
        $account = Account::factory()->for(User::factory())->withBalance(10000)->create();
        $stale = Account::find($account->id);

        $account->adjustBalance(-1000, 'expense');
        $stale->adjustBalance(-500, 'expense');

        $this->assertDatabaseHas('balance_history', [
            'account_id' => $account->id,
            'amount' => 500,
            'balance_before' => 9000,
            'balance_after' => 8500,
        ]);
        $this->assertEquals(8500, $stale->balance);
    }
}
