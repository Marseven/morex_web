<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_accounts_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/accounts');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Accounts/Index'));
    }

    public function test_accounts_page_shows_user_accounts(): void
    {
        $accounts = Account::factory()->count(3)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/accounts');

        $response->assertInertia(fn ($page) => $page
            ->component('Accounts/Index')
            ->has('accounts', 3)
        );
    }

    public function test_accounts_page_does_not_show_other_users_accounts(): void
    {
        $otherUser = User::factory()->create();
        Account::factory()->count(2)->for($otherUser)->create();
        Account::factory()->count(1)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/accounts');

        $response->assertInertia(fn ($page) => $page
            ->has('accounts', 1)
        );
    }

    public function test_create_account_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/accounts/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Accounts/Create'));
    }

    public function test_user_can_create_account(): void
    {
        $response = $this->actingAs($this->user)->post('/accounts', [
            'name' => 'Test Account',
            'type' => 'current',
            'initial_balance' => 100000,
            'color' => '#FFFFFF',
            'icon' => 'wallet',
            'is_default' => false,
        ]);

        $response->assertRedirect('/accounts');
        $this->assertDatabaseHas('accounts', [
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'type' => 'current',
            'initial_balance' => 100000,
            'balance' => 100000,
        ]);
    }

    public function test_user_can_create_default_account(): void
    {
        Account::factory()->for($this->user)->default()->create();

        $response = $this->actingAs($this->user)->post('/accounts', [
            'name' => 'New Default Account',
            'type' => 'savings',
            'initial_balance' => 50000,
            'color' => '#10B981',
            'icon' => 'wallet',
            'is_default' => true,
        ]);

        $response->assertRedirect('/accounts');
        $this->assertDatabaseHas('accounts', [
            'name' => 'New Default Account',
            'is_default' => true,
        ]);

        // Previous default should be unset
        $this->assertEquals(1, $this->user->accounts()->where('is_default', true)->count());
    }

    public function test_account_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post('/accounts', [
            'type' => 'current',
            'initial_balance' => 100000,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_account_creation_requires_valid_type(): void
    {
        $response = $this->actingAs($this->user)->post('/accounts', [
            'name' => 'Test Account',
            'type' => 'invalid-type',
            'initial_balance' => 100000,
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_edit_account_page_can_be_rendered(): void
    {
        $account = Account::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get("/accounts/{$account->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounts/Edit')
            ->has('account')
        );
    }

    public function test_user_cannot_edit_other_users_account(): void
    {
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->get("/accounts/{$account->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_can_update_account(): void
    {
        $account = Account::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->put("/accounts/{$account->id}", [
            'name' => 'Updated Account Name',
            'type' => 'savings',
            'initial_balance' => 200000,
            'color' => '#EF4444',
            'icon' => 'wallet',
            'is_default' => false,
        ]);

        $response->assertRedirect('/accounts');
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Account Name',
            'type' => 'savings',
            'initial_balance' => 200000,
        ]);
    }

    public function test_user_cannot_update_other_users_account(): void
    {
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->put("/accounts/{$account->id}", [
            'name' => 'Hacked Name',
            'type' => 'current',
            'initial_balance' => 999999,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_account(): void
    {
        $account = Account::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->delete("/accounts/{$account->id}");

        $response->assertRedirect('/accounts');
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_user_cannot_delete_other_users_account(): void
    {
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->delete("/accounts/{$account->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_total_balance_is_calculated_correctly(): void
    {
        Account::factory()->for($this->user)->withBalance(100000)->create();
        Account::factory()->for($this->user)->withBalance(200000)->create();

        $response = $this->actingAs($this->user)->get('/accounts');

        $response->assertInertia(fn ($page) => $page
            ->where('totalBalance', 300000)
        );
    }
}
