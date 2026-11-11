<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Module rapprochement : schéma, catégories système d'écart et exclusion budgétaire (RM-4).
 */
class ReconciliationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_reconciliation_categories_exist_for_both_types(): void
    {
        $expense = Category::find(Category::RECONCILIATION_EXPENSE_ID);
        $income = Category::find(Category::RECONCILIATION_INCOME_ID);

        $this->assertSame(['expense', true, null], [$expense?->type, $expense?->is_system, $expense?->user_id]);
        $this->assertSame(['income', true, null], [$income?->type, $income?->is_system, $income?->user_id]);
    }

    public function test_reconciliation_tables_are_in_place(): void
    {
        $this->assertTrue(Schema::hasColumn('transactions', 'reconciled_at'));
        $this->assertTrue(Schema::hasColumns('reconciliations', [
            'id', 'user_id', 'account_id', 'statement_balance', 'balance_before',
            'difference', 'adjustment_transaction_id', 'reconciled_at', 'note', 'deleted_at',
        ]));
    }

    public function test_reconciliation_gaps_are_left_out_of_budget_cycles(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->withBalance(100000)->create();
        $cycle = new BudgetCycle([
            'start_date' => now()->subDays(5)->toDateString(),
            'period_name' => 'Cycle de test',
            'status' => 'active',
        ]);
        $cycle->user_id = $user->id;
        $cycle->save();

        Transaction::factory()->for($user)->create([
            'account_id' => $account->id, 'type' => 'expense', 'amount' => 3000,
            'date' => now()->toDateString(),
        ]);
        Transaction::factory()->for($user)->create([
            'account_id' => $account->id, 'type' => 'expense', 'amount' => 50000,
            'category_id' => Category::RECONCILIATION_EXPENSE_ID,
            'date' => now()->toDateString(),
        ]);

        $this->assertSame(3000, (int) $cycle->transactions()->where('type', 'expense')->sum('amount'));
    }
}
