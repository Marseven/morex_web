<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->assertInstanceOf(User::class, $account->user);
        $this->assertEquals($user->id, $account->user->id);
    }

    public function test_account_has_uuid_as_primary_key(): void
    {
        $account = Account::factory()->create();

        $this->assertIsString($account->id);
        $this->assertEquals(36, strlen($account->id));
    }

    public function test_account_has_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->count(5)->for($user)->for($account)->create();

        $this->assertCount(5, $account->transactions);
    }

    public function test_account_recalculates_balance_with_income(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(100000)->create();

        Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->withAmount(50000)
            ->create();

        $account->refresh();
        $this->assertEquals(150000, $account->balance);
    }

    public function test_account_recalculates_balance_with_expense(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(100000)->create();

        Transaction::factory()
            ->for($user)
            ->for($account)
            ->expense()
            ->withAmount(30000)
            ->create();

        $account->refresh();
        $this->assertEquals(70000, $account->balance);
    }

    public function test_account_recalculates_balance_with_transfer_out(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->withBalance(100000)->create();
        $destAccount = Account::factory()->for($user)->withBalance(50000)->create();

        Transaction::factory()
            ->for($user)
            ->for($sourceAccount)
            ->state([
                'type' => 'transfer',
                'amount' => 25000,
                'transfer_to_account_id' => $destAccount->id,
                'category_id' => null,
            ])
            ->create();

        $sourceAccount->refresh();
        $destAccount->refresh();

        $this->assertEquals(75000, $sourceAccount->balance);
        $this->assertEquals(75000, $destAccount->balance);
    }

    public function test_account_casts_balance_to_integer(): void
    {
        $account = Account::factory()->withBalance(100000)->create();

        $this->assertIsInt($account->balance);
        $this->assertIsInt($account->initial_balance);
    }

    public function test_account_casts_is_default_to_boolean(): void
    {
        $account = Account::factory()->default()->create();

        $this->assertIsBool($account->is_default);
        $this->assertTrue($account->is_default);
    }

    public function test_account_can_be_of_different_types(): void
    {
        $types = ['current', 'savings', 'cash', 'credit', 'investment'];

        foreach ($types as $type) {
            $account = Account::factory()->create(['type' => $type]);
            $this->assertEquals($type, $account->type);
        }
    }
}
