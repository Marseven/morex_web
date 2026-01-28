<?php

namespace Tests\Unit\Models;

use App\Models\Debt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_debt_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $debt = Debt::factory()->for($user)->create();

        $this->assertInstanceOf(User::class, $debt->user);
        $this->assertEquals($user->id, $debt->user->id);
    }

    public function test_debt_has_uuid_as_primary_key(): void
    {
        $debt = Debt::factory()->create();

        $this->assertIsString($debt->id);
        $this->assertEquals(36, strlen($debt->id));
    }

    public function test_debt_progress_percentage(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 75000,
        ]);

        $this->assertEquals(25.0, $debt->progress_percentage);
    }

    public function test_debt_progress_percentage_when_fully_paid(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 0,
        ]);

        $this->assertEquals(100.0, $debt->progress_percentage);
    }

    public function test_debt_progress_percentage_with_zero_initial(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 0,
            'current_amount' => 0,
        ]);

        $this->assertEquals(100, $debt->progress_percentage);
    }

    public function test_debt_remaining_amount(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 40000,
        ]);

        $this->assertEquals(40000, $debt->remaining_amount);
    }

    public function test_debt_paid_amount(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 40000,
        ]);

        $this->assertEquals(60000, $debt->paid_amount);
    }

    public function test_debt_days_until_due(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => Carbon::now()->addDays(15),
        ]);

        // Allow for time differences (14-15 days depending on exact time)
        $this->assertGreaterThanOrEqual(14, $debt->days_until_due);
        $this->assertLessThanOrEqual(15, $debt->days_until_due);
    }

    public function test_debt_days_until_due_is_null_without_due_date(): void
    {
        $debt = Debt::factory()->create(['due_date' => null]);

        $this->assertNull($debt->days_until_due);
    }

    public function test_debt_is_overdue_when_past_due_date(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);

        $this->assertTrue($debt->is_overdue);
    }

    public function test_debt_is_not_overdue_when_paid(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => Carbon::now()->subDays(5),
            'status' => 'paid',
        ]);

        $this->assertFalse($debt->is_overdue);
    }

    public function test_debt_is_not_overdue_without_due_date(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => null,
            'status' => 'active',
        ]);

        $this->assertFalse($debt->is_overdue);
    }

    public function test_debt_is_not_overdue_when_future(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => Carbon::now()->addDays(10),
            'status' => 'active',
        ]);

        $this->assertFalse($debt->is_overdue);
    }

    public function test_debt_add_payment(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 100000,
            'status' => 'active',
        ]);

        $debt->addPayment(30000);

        $this->assertEquals(70000, $debt->current_amount);
        $this->assertEquals('active', $debt->status);
    }

    public function test_debt_add_payment_marks_as_paid_when_complete(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 30000,
            'status' => 'active',
        ]);

        $debt->addPayment(30000);

        $this->assertEquals(0, $debt->current_amount);
        $this->assertEquals('paid', $debt->status);
    }

    public function test_debt_add_payment_cannot_go_negative(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 20000,
            'status' => 'active',
        ]);

        $debt->addPayment(50000);

        $this->assertEquals(0, $debt->current_amount);
        $this->assertEquals('paid', $debt->status);
    }

    public function test_debt_types(): void
    {
        $debt = Debt::factory()->debt()->create();
        $credit = Debt::factory()->credit()->create();

        $this->assertEquals('debt', $debt->type);
        $this->assertEquals('credit', $credit->type);
    }

    public function test_debt_statuses(): void
    {
        $activeDebt = Debt::factory()->create(['status' => 'active']);
        $paidDebt = Debt::factory()->paid()->create();

        $this->assertEquals('active', $activeDebt->status);
        $this->assertEquals('paid', $paidDebt->status);
    }

    public function test_debt_casts_amounts_to_integer(): void
    {
        $debt = Debt::factory()->create([
            'initial_amount' => 100000,
            'current_amount' => 50000,
        ]);

        $this->assertIsInt($debt->initial_amount);
        $this->assertIsInt($debt->current_amount);
    }

    public function test_debt_casts_due_date(): void
    {
        $debt = Debt::factory()->create([
            'due_date' => '2026-06-30',
        ]);

        $this->assertInstanceOf(Carbon::class, $debt->due_date);
    }

    public function test_debt_has_contact_info(): void
    {
        $debt = Debt::factory()->create([
            'contact_name' => 'John Doe',
            'contact_phone' => '+237 699 123 456',
        ]);

        $this->assertEquals('John Doe', $debt->contact_name);
        $this->assertEquals('+237 699 123 456', $debt->contact_phone);
    }
}
