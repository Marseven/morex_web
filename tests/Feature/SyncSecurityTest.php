<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couvre les corrections de sécurité et d'intégrité :
 * - IDOR : impossible de rattacher une donnée au compte/catégorie d'autrui (sync + API + webhook)
 * - Solde : un update via sync ne double-compte pas le mouvement
 */
class SyncSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_push_rejects_transaction_on_another_users_account(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->for($victim)->withBalance(100000)->create();

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/sync/push', [
            'changes' => [[
                'type' => 'transaction',
                'action' => 'create',
                'local_id' => 'local-1',
                'data' => [
                    'amount' => 50000,
                    'type' => 'expense',
                    'account_id' => $victimAccount->id, // compte de la victime
                    'date' => '2026-07-01',
                ],
                'updated_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertOk();
        // La modification est rejetée en erreur, pas appliquée
        $this->assertNotEmpty($response->json('results.errors'));
        $this->assertEmpty($response->json('results.processed'));

        // Le solde de la victime est intact et aucune transaction n'a été créée sur son compte
        $victimAccount->refresh();
        $this->assertEquals(100000, $victimAccount->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_sync_push_update_does_not_double_count_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(0)->create();

        Sanctum::actingAs($user);

        // Création d'une dépense de 1000 → solde -1000
        $create = $this->postJson('/api/sync/push', [
            'changes' => [[
                'type' => 'transaction',
                'action' => 'create',
                'local_id' => 'local-1',
                'data' => [
                    'amount' => 1000,
                    'type' => 'expense',
                    'account_id' => $account->id,
                    'date' => '2026-07-01',
                ],
                'updated_at' => now()->toIso8601String(),
            ]],
        ]);
        $create->assertOk();
        $serverId = $create->json('results.processed.0.server_id');
        $this->assertNotNull($serverId);

        $account->refresh();
        $this->assertEquals(-1000, $account->balance);

        // Mise à jour du montant 1000 → 2000. Le solde doit devenir -2000 (et non -3000).
        $update = $this->postJson('/api/sync/push', [
            'changes' => [[
                'type' => 'transaction',
                'action' => 'update',
                'local_id' => 'local-1',
                'server_id' => $serverId,
                'data' => [
                    'amount' => 2000,
                    'type' => 'expense',
                    'account_id' => $account->id,
                    'date' => '2026-07-01',
                ],
                'updated_at' => now()->addMinute()->toIso8601String(),
            ]],
        ]);
        $update->assertOk();

        $account->refresh();
        $this->assertEquals(-2000, $account->balance);
    }

    public function test_api_transaction_rejects_another_users_private_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(100000)->create();

        $otherUser = User::factory()->create();
        $privateCategory = Category::factory()->for($otherUser)->expense()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/transactions', [
            'amount' => 1000,
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $privateCategory->id, // catégorie privée d'un autre utilisateur
            'date' => '2026-07-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_sms_rejects_another_users_account(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $victimAccount = Account::factory()->for($victim)->withBalance(100000)->create();

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/webhook/sms', [
            'message' => 'Votre compte a ete credite de 5000 FCFA. De: John',
            'sender' => 'Airtel',
            'account_id' => $victimAccount->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('transactions', 0);
    }
}
