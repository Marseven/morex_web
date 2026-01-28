<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_debts_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/debts');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Debts/Index'));
    }

    public function test_debts_page_shows_user_debts(): void
    {
        Debt::factory()->count(4)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/debts');

        $response->assertInertia(fn ($page) => $page
            ->component('Debts/Index')
            ->has('debts', 4)
        );
    }

    public function test_debts_page_shows_stats(): void
    {
        Debt::factory()->for($this->user)->debt()->withAmount(100000)->create();
        Debt::factory()->for($this->user)->credit()->withAmount(50000)->create();

        $response = $this->actingAs($this->user)->get('/debts');

        $response->assertInertia(fn ($page) => $page
            ->has('stats')
            ->has('stats.total_debt')
            ->has('stats.total_credit')
            ->has('stats.active_debts')
            ->has('stats.active_credits')
            ->has('stats.overdue_count')
        );
    }

    public function test_debts_page_does_not_show_other_users_debts(): void
    {
        $otherUser = User::factory()->create();
        Debt::factory()->count(3)->for($otherUser)->create();
        Debt::factory()->count(2)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get('/debts');

        $response->assertInertia(fn ($page) => $page
            ->has('debts', 2)
        );
    }

    public function test_create_debt_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/debts/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Debts/Create'));
    }

    public function test_user_can_create_debt(): void
    {
        $response = $this->actingAs($this->user)->post('/debts', [
            'name' => 'Loan from friend',
            'type' => 'debt',
            'initial_amount' => 150000,
            'due_date' => '2026-06-30',
            'contact_name' => 'John Doe',
            'contact_phone' => '+237 699 123 456',
            'description' => 'Personal loan',
            'color' => '#EF4444',
        ]);

        $response->assertRedirect('/debts');
        $this->assertDatabaseHas('debts', [
            'user_id' => $this->user->id,
            'name' => 'Loan from friend',
            'type' => 'debt',
            'initial_amount' => 150000,
            'current_amount' => 150000,
            'status' => 'active',
        ]);
    }

    public function test_user_can_create_credit(): void
    {
        $response = $this->actingAs($this->user)->post('/debts', [
            'name' => 'Money lent to colleague',
            'type' => 'credit',
            'initial_amount' => 75000,
            'contact_name' => 'Jane Smith',
            'color' => '#10B981',
        ]);

        $response->assertRedirect('/debts');
        $this->assertDatabaseHas('debts', [
            'user_id' => $this->user->id,
            'name' => 'Money lent to colleague',
            'type' => 'credit',
            'initial_amount' => 75000,
        ]);
    }

    public function test_debt_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post('/debts', [
            'type' => 'debt',
            'initial_amount' => 100000,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_debt_creation_requires_valid_type(): void
    {
        $response = $this->actingAs($this->user)->post('/debts', [
            'name' => 'Test Debt',
            'type' => 'invalid',
            'initial_amount' => 100000,
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_edit_debt_page_can_be_rendered(): void
    {
        $debt = Debt::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get("/debts/{$debt->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Debts/Edit')
            ->has('debt')
        );
    }

    public function test_user_cannot_edit_other_users_debt(): void
    {
        $otherUser = User::factory()->create();
        $debt = Debt::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->get("/debts/{$debt->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_can_update_debt(): void
    {
        $debt = Debt::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->put("/debts/{$debt->id}", [
            'name' => 'Updated Debt',
            'type' => 'debt',
            'initial_amount' => 200000,
            'current_amount' => 100000,
            'due_date' => '2026-08-15',
            'contact_name' => 'Updated Contact',
            'status' => 'active',
            'color' => '#F59E0B',
        ]);

        $response->assertRedirect('/debts');
        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'name' => 'Updated Debt',
            'initial_amount' => 200000,
        ]);
    }

    public function test_user_cannot_update_other_users_debt(): void
    {
        $otherUser = User::factory()->create();
        $debt = Debt::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->put("/debts/{$debt->id}", [
            'name' => 'Hacked Debt',
            'type' => 'debt',
            'initial_amount' => 999999,
            'current_amount' => 0,
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_add_payment_to_debt(): void
    {
        $debt = Debt::factory()->for($this->user)->create([
            'initial_amount' => 100000,
            'current_amount' => 100000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post("/debts/{$debt->id}/payment", [
            'amount' => 30000,
        ]);

        $response->assertRedirect();
        $debt->refresh();
        $this->assertEquals(70000, $debt->current_amount);
    }

    public function test_payment_marks_debt_as_paid_when_complete(): void
    {
        $debt = Debt::factory()->for($this->user)->create([
            'initial_amount' => 100000,
            'current_amount' => 30000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post("/debts/{$debt->id}/payment", [
            'amount' => 30000,
        ]);

        $response->assertRedirect();
        $debt->refresh();
        $this->assertEquals(0, $debt->current_amount);
        $this->assertEquals('paid', $debt->status);
    }

    public function test_user_cannot_add_payment_to_other_users_debt(): void
    {
        $otherUser = User::factory()->create();
        $debt = Debt::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->post("/debts/{$debt->id}/payment", [
            'amount' => 10000,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_debt(): void
    {
        $debt = Debt::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->delete("/debts/{$debt->id}");

        $response->assertRedirect('/debts');
        $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
    }

    public function test_user_cannot_delete_other_users_debt(): void
    {
        $otherUser = User::factory()->create();
        $debt = Debt::factory()->for($otherUser)->create();

        $response = $this->actingAs($this->user)->delete("/debts/{$debt->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('debts', ['id' => $debt->id]);
    }

    public function test_overdue_count_is_correct(): void
    {
        // Active overdue debt
        Debt::factory()->for($this->user)->overdue()->debt()->create();
        // Active overdue credit
        Debt::factory()->for($this->user)->overdue()->credit()->create();
        // Paid overdue (should not count)
        Debt::factory()->for($this->user)->create([
            'due_date' => Carbon::now()->subDays(5),
            'status' => 'paid',
        ]);
        // Future due date (should not count)
        Debt::factory()->for($this->user)->create([
            'due_date' => Carbon::now()->addDays(10),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get('/debts');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.overdue_count', 2)
        );
    }
}
