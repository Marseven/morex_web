<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module transferts-frais (3c) : RM-1 à RM-4.
 */
class TransferFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $airtel;

    private Account $wallet;

    private TransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 0));
        $this->app->instance(PushNotifier::class, new class implements PushNotifier
        {
            public function sendToUser(User $user, array $payload): int
            {
                return 0;
            }
        });
        $this->user = User::factory()->create();
        $this->airtel = Account::factory()->for($this->user)->withBalance(200000)->create(['name' => 'Compte Airtel Money']);
        $this->wallet = Account::factory()->for($this->user)->withBalance(10000)->create(['name' => 'Portefeuille']);
        $this->service = app(TransferService::class);
    }

    private function withdraw(int $amount = 50000, int $fee = 500): Transfer
    {
        return $this->service->create($this->user, [
            'from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id,
            'amount' => $amount, 'fee' => $fee, 'date' => now()->toDateString(),
        ]);
    }

    /** @return array{int, int} soldes Airtel, Portefeuille */
    private function balances(): array
    {
        return [(int) $this->airtel->fresh()->balance, (int) $this->wallet->fresh()->balance];
    }

    public function test_a_fee_is_a_separate_bank_fee_expense(): void
    {
        $transfer = $this->withdraw();

        $this->assertSame([149500, 60000], $this->balances());
        $fee = $transfer->feeTransaction;
        $this->assertSame([500, 'expense', Category::BANK_FEES_ID, $this->airtel->id], [$fee->amount, $fee->type, $fee->category_id, $fee->account_id]);
        $this->assertSame(50000, $transfer->amount);
    }

    public function test_a_transfer_without_fee_creates_no_expense(): void
    {
        $this->withdraw(fee: 0);

        $this->assertSame([150000, 60000], $this->balances());
        $this->assertSame(0, Transaction::count());
    }

    public function test_changing_the_fee_updates_its_expense(): void
    {
        $transfer = $this->withdraw();

        $this->service->update($transfer, ['fee' => 600]);
        $this->assertSame([149400, 60000], $this->balances());

        $this->service->update($transfer->fresh(), ['fee' => 0]);
        $this->assertSame([150000, 60000], $this->balances());
        $this->assertNull($transfer->fresh()->fee_transaction_id);

        $this->service->update($transfer->fresh(), ['fee' => 300]);
        $this->assertSame([149700, 60000], $this->balances());
    }

    public function test_moving_the_source_moves_the_fee(): void
    {
        $bgfi = Account::factory()->for($this->user)->withBalance(300000)->create(['name' => 'BGFI']);
        $transfer = $this->withdraw();

        $this->service->update($transfer, ['from_account_id' => $bgfi->id]);

        $this->assertSame([200000, 60000], $this->balances());
        $this->assertSame(249500, (int) $bgfi->fresh()->balance);
        $this->assertSame($bgfi->id, $transfer->fresh()->feeTransaction->account_id);
    }

    public function test_deleting_the_transfer_deletes_its_fee(): void
    {
        $transfer = $this->withdraw();

        $this->service->delete($transfer);

        $this->assertSame([200000, 10000], $this->balances());
        $this->assertSame(0, Transaction::count());
    }

    public function test_fees_count_in_cycle_spending(): void
    {
        $cycle = new BudgetCycle(['start_date' => '2026-11-01', 'period_name' => 'Nov', 'status' => 'active']);
        $cycle->user_id = $this->user->id;
        $cycle->save();

        $this->withdraw();

        $this->assertSame(500, $cycle->fresh()->calculateTotalExpense());
    }

    public function test_the_last_fee_of_the_same_pair_is_suggested(): void
    {
        $this->withdraw(fee: 500);
        $this->withdraw(fee: 700);

        $this->assertSame(700, $this->service->suggestedFee($this->user, $this->airtel->id, $this->wallet->id));
        $this->assertSame(0, $this->service->suggestedFee($this->user, $this->wallet->id, $this->airtel->id));
    }

    public function test_web_store_validates_the_fee(): void
    {
        $payload = ['from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id, 'amount' => 50000, 'date' => now()->toDateString()];

        $this->actingAs($this->user)->post('/transfers', $payload + ['fee' => -1])->assertSessionHasErrors('fee');
        $this->actingAs($this->user)->post('/transfers', $payload + ['fee' => 60000])->assertSessionHasErrors('fee');
        $this->actingAs($this->user)->post('/transfers', $payload + ['fee' => 500])->assertRedirect('/transfers');

        $this->assertSame([149500, 60000], $this->balances());
    }

    public function test_web_update_and_destroy_carry_the_fee(): void
    {
        $transfer = $this->withdraw();

        $this->actingAs($this->user)->put("/transfers/{$transfer->id}", [
            'from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id,
            'amount' => 50000, 'fee' => 800, 'date' => now()->toDateString(),
        ])->assertRedirect('/transfers');
        $this->assertSame([149200, 60000], $this->balances());

        $this->actingAs($this->user)->delete("/transfers/{$transfer->id}")->assertRedirect('/transfers');
        $this->assertSame([200000, 10000], $this->balances());
    }

    public function test_the_api_accepts_and_returns_the_fee(): void
    {
        Sanctum::actingAs($this->user);

        $id = $this->postJson('/api/transfers', ['from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id,
            'amount' => 50000, 'fee' => 500, 'date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('fee', 500)->json('id');

        $this->putJson("/api/transfers/{$id}", ['fee' => 0])->assertOk()->assertJsonPath('data.fee', 0);
        $this->assertSame([150000, 60000], $this->balances());

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/transfers/{$id}", ['fee' => 100])->assertForbidden();
    }

    public function test_import_reads_the_fee_column(): void
    {
        $this->actingAs($this->user)->post('/transfers/import', ['rows' => [
            ['date' => '2026-11-10', 'amount' => 50000, 'fee' => 500, 'from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id],
            ['date' => '2026-11-11', 'amount' => 20000, 'from_account_id' => $this->airtel->id, 'to_account_id' => $this->wallet->id],
        ]])->assertRedirect('/transfers');

        $this->assertSame([129500, 80000], $this->balances());
    }

    public function test_a_transfer_deleted_by_the_sync_drops_its_fee(): void
    {
        $transfer = $this->withdraw();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'transfer', 'action' => 'delete', 'server_id' => $transfer->id, 'local_id' => 't1',
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();

        $this->assertSame([200000, 10000], $this->balances());
        $this->assertSame(0, Transaction::count());
    }

    public function test_a_sync_update_without_fee_keeps_it(): void
    {
        $transfer = $this->withdraw();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/sync/push', ['changes' => [[
            'type' => 'transfer', 'action' => 'update', 'server_id' => $transfer->id, 'local_id' => 't1',
            'data' => ['description' => 'Retrait marché'], 'updated_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();

        $this->assertSame(500, $transfer->fresh()->fee);
        $this->assertSame([149500, 60000], $this->balances());
    }
}
