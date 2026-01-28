<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_budgets_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/budgets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Budgets/Index'));
    }

    public function test_budgets_page_shows_user_categories(): void
    {
        Category::factory()->count(5)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/budgets');

        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Index')
            ->has('categories', 5)
        );
    }

    public function test_budgets_page_does_not_show_other_users_categories(): void
    {
        $otherUser = User::factory()->create();
        Category::factory()->count(3)->for($otherUser)->create();
        Category::factory()->count(2)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/budgets');

        $response->assertInertia(fn ($page) => $page
            ->has('categories', 2)
        );
    }

    public function test_categories_include_spent_this_month(): void
    {
        $account = Account::factory()->for($this->user)->withBalance(500000)->create();
        $category = Category::factory()->for($this->user)->expense()->withBudget(100000)->create();

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->expense()
            ->withAmount(35000)
            ->onDate(Carbon::now()->format('Y-m-d'))
            ->create();

        $response = $this->actingAs($this->user)->get('/budgets');

        $response->assertInertia(fn ($page) => $page
            ->has('categories.0.spent_this_month')
        );
    }

    public function test_create_category_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/budgets/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Budgets/Create'));
    }

    public function test_user_can_create_expense_category(): void
    {
        $response = $this->actingAs($this->user)->post('/budgets', [
            'name' => 'Alimentation',
            'type' => 'expense',
            'color' => '#10B981',
            'icon' => 'cart',
            'budget_limit' => 150000,
        ]);

        $response->assertRedirect('/budgets');
        $this->assertDatabaseHas('categories', [
            'user_id' => $this->user->id,
            'name' => 'Alimentation',
            'type' => 'expense',
            'budget_limit' => 150000,
        ]);
    }

    public function test_user_can_create_income_category(): void
    {
        $response = $this->actingAs($this->user)->post('/budgets', [
            'name' => 'Freelance',
            'type' => 'income',
            'color' => '#3B82F6',
            'icon' => 'briefcase',
        ]);

        $response->assertRedirect('/budgets');
        $this->assertDatabaseHas('categories', [
            'user_id' => $this->user->id,
            'name' => 'Freelance',
            'type' => 'income',
        ]);
    }

    public function test_category_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post('/budgets', [
            'type' => 'expense',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_category_creation_requires_valid_type(): void
    {
        $response = $this->actingAs($this->user)->post('/budgets', [
            'name' => 'Test Category',
            'type' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_edit_category_page_can_be_rendered(): void
    {
        $category = Category::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get("/budgets/{$category->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Edit')
            ->has('category')
        );
    }

    public function test_user_cannot_edit_other_users_category(): void
    {
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->get("/budgets/{$category->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_can_update_category(): void
    {
        $category = Category::factory()->for($this->user)->expense()->create();

        $response = $this->actingAs($this->user)->put("/budgets/{$category->id}", [
            'name' => 'Updated Category',
            'type' => 'expense',
            'color' => '#EF4444',
            'icon' => 'tag',
            'budget_limit' => 200000,
        ]);

        $response->assertRedirect('/budgets');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'budget_limit' => 200000,
        ]);
    }

    public function test_user_cannot_update_other_users_category(): void
    {
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->put("/budgets/{$category->id}", [
            'name' => 'Hacked Category',
            'type' => 'expense',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_category(): void
    {
        $category = Category::factory()->for($this->user)->create(['is_system' => false]);

        $response = $this->actingAs($this->user)->delete("/budgets/{$category->id}");

        $response->assertRedirect('/budgets');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_system_category(): void
    {
        $category = Category::factory()->for($this->user)->system()->create();

        $response = $this->actingAs($this->user)->delete("/budgets/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_other_users_category(): void
    {
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->delete("/budgets/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
