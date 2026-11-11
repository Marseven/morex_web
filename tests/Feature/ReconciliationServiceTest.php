<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Module rapprochement : RM-1 (écart tracé), RM-2 (non pointées), RM-5 (formule d'écart).
 */
class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private ReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->for($this->user)->withBalance(100000)->create();
        $this->service = app(ReconciliationService::class);
    }

    private function tx(string $type, int $amount, array $extra = []): Transaction
    {
        // $extra en premier : l'union de tableaux garde les clés de gauche
        return Transaction::factory()->for($this->user)->create($extra + [
            'account_id' => $this->account->id, 'type' => $type, 'amount' => $amount,
            'date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_it_lists_only_unreconciled_confirmed_transactions_of_the_account(): void
    {
        $open = $this->tx('expense', 1500);
        $this->tx('expense', 700, ['status' => Transaction::STATUS_PENDING]);
        $this->tx('income', 900)->forceFill(['reconciled_at' => now()])->save();
        $this->tx('expense', 200)->delete();
        Transaction::factory()->for($this->user)->create(['type' => 'expense', 'amount' => 50]);

        $ids = $this->service->uncleared($this->account)->pluck('id')->all();

        $this->assertSame([$open->id], $ids);
    }

    public function test_a_positive_gap_becomes_a_traced_income(): void
    {
        $a = $this->tx('income', 2000);
        $b = $this->tx('expense', 1500);
        $balance = $this->account->refresh()->balance; // 100 500

        $reconciliation = $this->service->reconcile($this->account, $balance + 2300, [$a->id, $b->id]);

        $this->assertSame(2300, $reconciliation->difference);
        $this->assertSame($balance, $reconciliation->balance_before);
        $gap = $reconciliation->adjustmentTransaction;
        $this->assertSame(['income', 2300, Category::RECONCILIATION_INCOME_ID], [$gap->type, $gap->amount, $gap->category_id]);
        $this->assertNotNull($gap->reconciled_at);
        $this->assertSame($balance + 2300, $this->account->refresh()->balance);
        $this->assertNotNull($a->refresh()->reconciled_at);
        $this->assertNotNull($b->refresh()->reconciled_at);
    }

    public function test_a_negative_gap_becomes_a_traced_expense(): void
    {
        $reconciliation = $this->service->reconcile($this->account, 98000, []);

        $this->assertSame(-2000, $reconciliation->difference);
        $this->assertSame(['expense', 2000, Category::RECONCILIATION_EXPENSE_ID], [
            $reconciliation->adjustmentTransaction->type,
            $reconciliation->adjustmentTransaction->amount,
            $reconciliation->adjustmentTransaction->category_id,
        ]);
        $this->assertSame(98000, $this->account->refresh()->balance);
    }

    public function test_unchecked_transactions_are_not_compensated_rm5(): void
    {
        $checked = $this->tx('expense', 1500);
        $notYetOnStatement = $this->tx('expense', 4000);
        $balance = $this->account->refresh()->balance; // 94 500

        // Le relevé ne montre pas encore la dépense de 4 000 : il affiche 98 500
        $reconciliation = $this->service->reconcile($this->account, 98500, [$checked->id]);

        $this->assertSame(0, $reconciliation->difference);
        $this->assertNull($reconciliation->adjustment_transaction_id);
        $this->assertSame($balance, $this->account->refresh()->balance);
        $this->assertNull($notYetOnStatement->refresh()->reconciled_at);
    }

    public function test_a_zero_gap_records_a_reconciliation_without_transaction(): void
    {
        $count = Transaction::count();

        $reconciliation = $this->service->reconcile($this->account, 100000, [], 'Relevé novembre');

        $this->assertSame(0, $reconciliation->difference);
        $this->assertSame('Relevé novembre', $reconciliation->note);
        $this->assertSame($count, Transaction::count());
        $this->assertSame(1, Reconciliation::count());
    }

    public function test_it_refuses_a_transaction_from_another_account(): void
    {
        $other = Transaction::factory()->for($this->user)->create(['type' => 'expense', 'amount' => 10]);

        $this->expectException(ValidationException::class);

        try {
            $this->service->reconcile($this->account, 100000, [$other->id]);
        } finally {
            $this->assertNull($other->refresh()->reconciled_at);
            $this->assertSame(0, Reconciliation::count());
        }
    }

    public function test_it_refuses_an_already_reconciled_transaction(): void
    {
        $done = $this->tx('expense', 500);
        $done->forceFill(['reconciled_at' => now()->subWeek()])->save();

        $this->expectException(ValidationException::class);

        $this->service->reconcile($this->account, 100000, [$done->id]);
    }

    public function test_adjust_to_balance_now_goes_through_a_traced_gap(): void
    {
        $this->account->adjustToBalance(105000);

        $this->assertSame(105000, $this->account->refresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $this->account->id, 'category_id' => Category::RECONCILIATION_INCOME_ID, 'amount' => 5000,
        ]);
        $this->assertSame(1, Reconciliation::count());
    }

    public function test_a_future_dated_transaction_is_listed_and_never_becomes_a_gap(): void
    {
        $rent = $this->tx('expense', 50000, ['date' => now()->addDay()->toDateString()]);
        $this->assertSame(50000, $this->account->refresh()->balance);

        $this->assertContains($rent->id, $this->service->uncleared($this->account)->pluck('id')->all());

        // Le relevé ne montre pas encore le loyer de demain : 100 000
        $reconciliation = $this->service->reconcile($this->account, 100000, []);

        $this->assertSame(0, $reconciliation->difference);
        $this->assertSame(50000, $this->account->refresh()->balance);
    }

    public function test_transactions_older_than_the_window_count_as_cleared(): void
    {
        ReconciliationService::$windowSize = 2;
        $oldest = $this->tx('expense', 1000, ['date' => now()->subDays(10)->toDateString()]);
        $recentA = $this->tx('expense', 200, ['date' => now()->subDays(2)->toDateString()]);
        $recentB = $this->tx('expense', 300, ['date' => now()->subDay()->toDateString()]);

        try {
            $listed = $this->service->uncleared($this->account)->pluck('id')->all();
            $this->assertSame([$recentA->id, $recentB->id], $listed);
            $this->assertSame(1, $this->service->olderThanWindowCount($this->account));

            // Seul recentA est sur le relevé ; recentB (−300) n'y est pas encore
            $reconciliation = $this->service->reconcile($this->account, 98800, [$recentA->id]);

            $this->assertSame(0, $reconciliation->difference);
            $this->assertNotNull($oldest->refresh()->reconciled_at);
            $this->assertNull($recentB->refresh()->reconciled_at);
        } finally {
            ReconciliationService::$windowSize = 500;
        }
    }
}
