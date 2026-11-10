<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\StatementEntry;
use App\Models\Account;
use App\Models\StatementLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StatementMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module releves-sms (3a) : RM-1 (correspondance), RM-3 (unicité), rejeu sans doublon.
 */
class StatementMatcherTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $airtel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->airtel = Account::factory()->for($this->user)->withBalance(500000)->create(['name' => 'Compte Airtel Money']);
    }

    private function tx(int $amount, string $date, string $type = 'expense', string $label = 'note', ?Account $account = null): Transaction
    {
        return Transaction::factory()->for($this->user)->create([
            'account_id' => ($account ?? $this->airtel)->id, 'type' => $type, 'amount' => $amount,
            'beneficiary' => $label, 'date' => $date,
        ]);
    }

    private function entry(int $amount, string $date, int $fee = 0, ?string $ref = null, string $label = 'RELEVE', int $index = 1): StatementEntry
    {
        return new StatementEntry($index, $date, $amount, $fee, $label, $ref, 'ok', '');
    }

    /** @return array<string, mixed> */
    private function match(array $entries): array
    {
        return app(StatementMatcher::class)->match($this->airtel, $entries);
    }

    public function test_an_equal_amount_on_the_same_day_is_matched(): void
    {
        $tx = $this->tx(10000, '2026-10-18');

        $row = $this->match([$this->entry(-10000, '2026-10-18', fee: 450)])['lines'][0];

        $this->assertSame(['matched', $tx->id, 450], [$row['status'], $row['transaction_id'], $row['fee']]);
    }

    public function test_the_gap_becomes_the_fee_when_the_statement_shows_the_total(): void
    {
        $tx = $this->tx(15500, '2026-10-24', label: 'restaurant');

        $row = $this->match([$this->entry(-16047, '2026-10-24', label: 'DIGITECH AFRICA')])['lines'][0];

        $this->assertSame(['matched', $tx->id, 547], [$row['status'], $row['transaction_id'], $row['fee']]);
    }

    public function test_a_gap_above_the_fee_ceiling_is_not_a_match(): void
    {
        $this->tx(15500, '2026-10-24');

        // 155 000 noté au lieu de 15 500 : jamais rapprochés
        $row = $this->match([$this->entry(-155000, '2026-10-24')])['lines'][0];

        $this->assertSame('no_transaction', $row['status']);
    }

    public function test_one_day_of_tolerance_but_not_more(): void
    {
        $this->tx(5000, '2026-10-20');

        $this->assertSame('matched', $this->match([$this->entry(-5000, '2026-10-21')])['lines'][0]['status']);
        $this->assertSame('no_transaction', $this->match([$this->entry(-5000, '2026-10-23')])['lines'][0]['status']);
    }

    public function test_direction_and_account_must_agree(): void
    {
        $this->tx(5000, '2026-10-20', type: 'income');
        $other = Account::factory()->for($this->user)->create();
        $this->tx(5000, '2026-10-20', account: $other);

        $this->assertSame('no_transaction', $this->match([$this->entry(-5000, '2026-10-20')])['lines'][0]['status']);
    }

    public function test_several_candidates_must_be_validated(): void
    {
        $a = $this->tx(2000, '2026-10-20', label: 'taxi');
        $b = $this->tx(2000, '2026-10-21', label: 'bus');

        $row = $this->match([$this->entry(-2000, '2026-10-20')])['lines'][0];

        $this->assertSame('to_validate', $row['status']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($row['candidates'], 'id'));
    }

    public function test_a_transaction_is_used_by_one_line_only(): void
    {
        $this->tx(3000, '2026-10-20');

        $rows = $this->match([$this->entry(-3000, '2026-10-20', index: 1), $this->entry(-3000, '2026-10-20', index: 2)])['lines'];

        $this->assertSame(['matched', 'no_transaction'], array_column($rows, 'status'));
    }

    public function test_exact_amounts_win_over_fee_based_matches(): void
    {
        $exact = $this->tx(10450, '2026-10-18');
        $withFee = $this->tx(10000, '2026-10-18');

        $rows = $this->match([$this->entry(-10450, '2026-10-18', index: 1), $this->entry(-10450, '2026-10-18', index: 2)])['lines'];

        $this->assertSame([$exact->id, $withFee->id], array_column($rows, 'transaction_id'));
        $this->assertSame([0, 450], array_column($rows, 'fee'));
    }

    public function test_lines_already_imported_are_recognised(): void
    {
        $line = new StatementLine(['account_id' => $this->airtel->id, 'date' => '2026-10-18', 'amount' => -10000, 'label' => 'X', 'external_ref' => 'CI1', 'source' => 'paste']);
        $line->user_id = $this->user->id;
        $line->save();

        $row = $this->match([$this->entry(-10000, '2026-10-18', ref: 'CI1')])['lines'][0];

        $this->assertSame('already_imported', $row['status']);
    }

    public function test_transactions_missing_from_the_statement_are_listed(): void
    {
        $missing = $this->tx(8000, '2026-10-19', label: 'courses');
        $this->tx(9000, '2026-09-01', label: 'hors période');

        $result = $this->match([$this->entry(-1, '2026-10-18'), $this->entry(-2, '2026-10-20', index: 2)]);

        $this->assertSame([$missing->id], array_column($result['missing'], 'id'));
    }

    public function test_unparsed_lines_pass_through(): void
    {
        $row = $this->match([StatementEntry::unparsed(3, 'Promo')])['lines'][0];

        $this->assertSame(['unparsed', 'Promo'], [$row['status'], $row['excerpt']]);
    }
}
