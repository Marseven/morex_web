<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $this->category = Category::factory()->for($this->user)->expense()->create();
    }

    public function test_transactions_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/transactions');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Transactions/Index'));
    }

    public function test_transactions_page_shows_user_transactions(): void
    {
        Transaction::factory()
            ->count(5)
            ->for($this->user)
            ->for($this->account)
            ->for($this->category)
            ->create();

        $response = $this->actingAs($this->user)->get('/transactions');

        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 5)
        );
    }

    public function test_transactions_are_paginated(): void
    {
        Transaction::factory()
            ->count(25)
            ->for($this->user)
            ->for($this->account)
            ->for($this->category)
            ->create();

        $response = $this->actingAs($this->user)->get('/transactions');

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.last_page')
        );
    }

    public function test_transactions_can_be_filtered_by_type(): void
    {
        Transaction::factory()
            ->count(3)
            ->for($this->user)
            ->for($this->account)
            ->for($this->category)
            ->expense()
            ->create();

        $incomeCategory = Category::factory()->for($this->user)->income()->create();
        Transaction::factory()
            ->count(2)
            ->for($this->user)
            ->for($this->account)
            ->for($incomeCategory)
            ->income()
            ->create();

        $response = $this->actingAs($this->user)->get('/transactions?type=expense');

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 3)
        );
    }

    public function test_transactions_can_be_filtered_by_account(): void
    {
        $secondAccount = Account::factory()->for($this->user)->create();

        Transaction::factory()
            ->count(3)
            ->for($this->user)
            ->for($this->account)
            ->create();

        Transaction::factory()
            ->count(2)
            ->for($this->user)
            ->for($secondAccount)
            ->create();

        $response = $this->actingAs($this->user)->get("/transactions?account_id={$this->account->id}");

        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 3)
        );
    }

    public function test_create_transaction_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/transactions/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Create')
            ->has('accounts')
            ->has('categories')
        );
    }

    public function test_user_can_create_expense_transaction(): void
    {
        $response = $this->actingAs($this->user)->post('/transactions', [
            'amount' => 25000,
            'type' => 'expense',
            'category_id' => $this->category->id,
            'account_id' => $this->account->id,
            'beneficiary' => 'Supermarket',
            'description' => 'Weekly groceries',
            'date' => '2026-01-27',
        ]);

        $response->assertRedirect('/transactions');
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 25000,
            'type' => 'expense',
            'beneficiary' => 'Supermarket',
        ]);

        // Check account balance updated
        $this->account->refresh();
        $this->assertEquals(475000, $this->account->balance);
    }

    public function test_user_can_create_income_transaction(): void
    {
        $incomeCategory = Category::factory()->for($this->user)->income()->create();

        $response = $this->actingAs($this->user)->post('/transactions', [
            'amount' => 1000000,
            'type' => 'income',
            'category_id' => $incomeCategory->id,
            'account_id' => $this->account->id,
            'beneficiary' => 'Company XYZ',
            'description' => 'Monthly salary',
            'date' => '2026-01-27',
        ]);

        $response->assertRedirect('/transactions');
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 1000000,
            'type' => 'income',
        ]);

        // Check account balance updated
        $this->account->refresh();
        $this->assertEquals(1500000, $this->account->balance);
    }

    public function test_user_can_create_transfer_transaction(): void
    {
        $destAccount = Account::factory()->for($this->user)->withBalance(100000)->create();

        $response = $this->actingAs($this->user)->post('/transactions', [
            'amount' => 50000,
            'type' => 'transfer',
            'account_id' => $this->account->id,
            'transfer_to_account_id' => $destAccount->id,
            'date' => '2026-01-27',
        ]);

        $response->assertRedirect('/transactions');

        // Check both account balances updated
        $this->account->refresh();
        $destAccount->refresh();
        $this->assertEquals(450000, $this->account->balance);
        $this->assertEquals(150000, $destAccount->balance);
    }

    public function test_transaction_creation_requires_amount(): void
    {
        $response = $this->actingAs($this->user)->post('/transactions', [
            'type' => 'expense',
            'account_id' => $this->account->id,
            'date' => '2026-01-27',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_transaction_creation_requires_positive_amount(): void
    {
        $response = $this->actingAs($this->user)->post('/transactions', [
            'amount' => -5000,
            'type' => 'expense',
            'account_id' => $this->account->id,
            'date' => '2026-01-27',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_user_can_update_transaction(): void
    {
        $transaction = Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($this->category)
            ->expense()
            ->withAmount(30000)
            ->create();

        $response = $this->actingAs($this->user)->put("/transactions/{$transaction->id}", [
            'amount' => 45000,
            'type' => 'expense',
            'category_id' => $this->category->id,
            'account_id' => $this->account->id,
            'beneficiary' => 'Updated beneficiary',
            'date' => '2026-01-27',
        ]);

        $response->assertRedirect('/transactions');
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 45000,
            'beneficiary' => 'Updated beneficiary',
        ]);
    }

    public function test_user_cannot_update_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $transaction = Transaction::factory()
            ->for($otherUser)
            ->for($otherAccount)
            ->create();

        $response = $this->actingAs($this->user)->put("/transactions/{$transaction->id}", [
            'amount' => 100000,
            'type' => 'expense',
            'account_id' => $this->account->id,
            'date' => '2026-01-27',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_transaction(): void
    {
        $transaction = Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($this->category)
            ->expense()
            ->withAmount(20000)
            ->create();

        $balanceAfterTransaction = $this->account->fresh()->balance;

        $response = $this->actingAs($this->user)->delete("/transactions/{$transaction->id}");

        $response->assertRedirect('/transactions');
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);

        // Balance should be restored
        $this->account->refresh();
        $this->assertEquals($balanceAfterTransaction + 20000, $this->account->balance);
    }

    public function test_user_cannot_delete_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        $transaction = Transaction::factory()
            ->for($otherUser)
            ->for($otherAccount)
            ->create();

        $response = $this->actingAs($this->user)->delete("/transactions/{$transaction->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }
}
