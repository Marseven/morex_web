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
 * Non-régression P2 : un delete rejoué par la synchro ne doit reverser le solde qu'une fois,
 * et un delete visant la transaction d'autrui ne doit jamais toucher son solde.
 */
class SyncDeleteIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function deleteChange(string $serverId): array
    {
        return ['changes' => [[
            'type' => 'transaction',
            'action' => 'delete',
            'local_id' => 'local-1',
            'server_id' => $serverId,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]]];
    }

    public function test_replayed_delete_reverses_the_balance_only_once(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(10000)->create();
        $transaction = Transaction::factory()->for($user)->create([
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 1000,
        ]);
        $this->assertEquals(9000, $account->refresh()->balance);

        Sanctum::actingAs($user);

        $this->postJson('/api/sync/push', $this->deleteChange($transaction->id))->assertOk();
        $this->assertEquals(10000, $account->refresh()->balance);

        // Réponse perdue côté mobile → le même delete est renvoyé
        $this->postJson('/api/sync/push', $this->deleteChange($transaction->id))
            ->assertOk()
            ->assertJsonPath('results.processed.0.action', 'already_deleted');

        $this->assertEquals(10000, $account->refresh()->balance);
    }

    public function test_replayed_create_moves_the_balance_only_once(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(10000)->create();
        Sanctum::actingAs($user);

        $create = ['changes' => [[
            'type' => 'transaction',
            'action' => 'create',
            'local_id' => 'local-1',
            'data' => ['amount' => 1000, 'type' => 'expense', 'account_id' => $account->id, 'date' => '2026-07-01'],
            'updated_at' => now()->toIso8601String(),
        ]]];

        $this->postJson('/api/sync/push', $create)->assertOk();
        // Réponse perdue côté mobile → le même create est renvoyé
        $this->postJson('/api/sync/push', $create)
            ->assertOk()
            ->assertJsonPath('results.processed.0.action', 'linked');

        $this->assertEquals(9000, $account->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_replayed_transfer_create_moves_the_balances_only_once(): void
    {
        $user = User::factory()->create();
        $from = Account::factory()->for($user)->withBalance(10000)->create();
        $to = Account::factory()->for($user)->withBalance(0)->create();
        Sanctum::actingAs($user);

        $create = ['changes' => [[
            'type' => 'transfer',
            'action' => 'create',
            'local_id' => 'local-t1',
            'data' => ['amount' => 3000, 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'date' => '2026-07-01'],
            'updated_at' => now()->toIso8601String(),
        ]]];

        $this->postJson('/api/sync/push', $create)->assertOk();
        $this->postJson('/api/sync/push', $create)->assertOk();

        $this->assertEquals(7000, $from->refresh()->balance);
        $this->assertEquals(3000, $to->refresh()->balance);
    }

    public function test_delete_of_another_users_transaction_leaves_their_balance_intact(): void
    {
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->for($victim)->withBalance(10000)->create();
        $victimTransaction = Transaction::factory()->for($victim)->create([
            'account_id' => $victimAccount->id,
            'type' => 'expense',
            'amount' => 1000,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/sync/push', $this->deleteChange($victimTransaction->id))->assertOk();

        $this->assertEquals(9000, $victimAccount->refresh()->balance);
        $this->assertNotSoftDeleted('transactions', ['id' => $victimTransaction->id]);
    }
}
