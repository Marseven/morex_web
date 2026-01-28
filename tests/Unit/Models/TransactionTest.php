<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertEquals($user->id, $transaction->user->id);
    }

    public function test_transaction_belongs_to_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        $this->assertInstanceOf(Account::class, $transaction->account);
        $this->assertEquals($account->id, $transaction->account->id);
    }

    public function test_transaction_belongs_to_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->create();

        $this->assertInstanceOf(Category::class, $transaction->category);
        $this->assertEquals($category->id, $transaction->category->id);
    }

    public function test_transaction_has_uuid_as_primary_key(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        $this->assertIsString($transaction->id);
        $this->assertEquals(36, strlen($transaction->id));
    }

    public function test_transaction_formatted_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->withAmount(1250000)
            ->create();

        $this->assertStringContainsString('FCFA', $transaction->formatted_amount);
    }

    public function test_transaction_signed_amount_for_income(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->income()
            ->withAmount(50000)
            ->create();

        $this->assertEquals(50000, $transaction->signed_amount);
    }

    public function test_transaction_signed_amount_for_expense(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->expense()
            ->withAmount(30000)
            ->create();

        $this->assertEquals(-30000, $transaction->signed_amount);
    }

    public function test_transaction_signed_amount_for_transfer(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->create();
        $destAccount = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()
            ->for($user)
            ->for($sourceAccount)
            ->state([
                'type' => 'transfer',
                'amount' => 25000,
                'transfer_to_account_id' => $destAccount->id,
                'category_id' => null,
            ])
            ->create();

        $this->assertEquals(-25000, $transaction->signed_amount);
    }

    public function test_transaction_transfer_has_destination_account(): void
    {
        $user = User::factory()->create();
        $sourceAccount = Account::factory()->for($user)->create();
        $destAccount = Account::factory()->for($user)->create();

        $transaction = Transaction::factory()
            ->for($user)
            ->for($sourceAccount)
            ->state([
                'type' => 'transfer',
                'amount' => 25000,
                'transfer_to_account_id' => $destAccount->id,
                'category_id' => null,
            ])
            ->create();

        $this->assertInstanceOf(Account::class, $transaction->transferToAccount);
        $this->assertEquals($destAccount->id, $transaction->transferToAccount->id);
    }

    public function test_transaction_casts_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->onDate('2026-01-15')
            ->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $transaction->date);
        $this->assertEquals('2026-01-15', $transaction->date->format('Y-m-d'));
    }

    public function test_transaction_updates_account_balance_on_delete(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(100000)->create();

        $transaction = Transaction::factory()
            ->for($user)
            ->for($account)
            ->expense()
            ->withAmount(30000)
            ->create();

        $account->refresh();
        $this->assertEquals(70000, $account->balance);

        $transaction->delete();
        $account->refresh();
        $this->assertEquals(100000, $account->balance);
    }
}
