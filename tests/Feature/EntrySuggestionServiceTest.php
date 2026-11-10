<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\ParsedNoteLine;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EntrySuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module saisie-lot : RM-4 (catégorie et compte d'après l'historique) et RM-5 (doublons).
 */
class EntrySuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cash;

    private Account $airtel;

    private Category $transport;

    private Category $outings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cash = Account::factory()->for($this->user)->withBalance(100000)->create(['name' => 'Portefeuille']);
        $this->airtel = Account::factory()->for($this->user)->withBalance(100000)->create(['name' => 'Airtel']);
        $this->transport = Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Transport', 'type' => 'expense']);
        $this->outings = Category::factory()->create(['user_id' => $this->user->id, 'name' => 'Sorties', 'type' => 'expense']);
    }

    private function past(string $label, Category $category, Account $account, int $daysAgo = 3, int $amount = 1000): void
    {
        Transaction::factory()->for($this->user)->create([
            'beneficiary' => $label, 'category_id' => $category->id, 'account_id' => $account->id,
            'type' => 'expense', 'amount' => $amount, 'date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    private function line(string $label, int $amount = 1500, string $type = 'expense', ?string $date = null): ParsedNoteLine
    {
        return new ParsedNoteLine(1, "$amount $label", $date ?? now()->toDateString(), $type, $amount, $label, 'ok');
    }

    /** @return array<string, mixed> */
    private function suggest(ParsedNoteLine $line, ?Account $default = null): array
    {
        return app(EntrySuggestionService::class)->preview($this->user, [$line], $default ?? $this->cash)[0];
    }

    public function test_it_uses_the_usual_category_and_account_of_the_label(): void
    {
        $this->past('Night Club', $this->outings, $this->airtel);
        $this->past('night club ', $this->outings, $this->airtel);
        $this->past('Night club', $this->transport, $this->cash);

        $row = $this->suggest($this->line('night club'));

        $this->assertSame([$this->outings->id, $this->airtel->id, 'ok'], [$row['category_id'], $row['account_id'], $row['status']]);
    }

    public function test_accents_and_case_do_not_matter(): void
    {
        $this->past('Café', $this->outings, $this->cash);

        $this->assertSame($this->outings->id, $this->suggest($this->line('CAFE'))['category_id']);
    }

    public function test_an_unknown_label_needs_a_category_and_takes_the_default_account(): void
    {
        $row = $this->suggest($this->line('karaoké'), $this->airtel);

        $this->assertSame([null, $this->airtel->id, 'incomplete'], [$row['category_id'], $row['account_id'], $row['status']]);
    }

    public function test_history_older_than_180_days_is_ignored(): void
    {
        $this->past('taxi', $this->transport, $this->cash, daysAgo: 200);

        $this->assertNull($this->suggest($this->line('taxi'))['category_id']);
    }

    public function test_reconciliation_gaps_are_never_suggested(): void
    {
        Transaction::factory()->for($this->user)->create([
            'beneficiary' => 'ajustement', 'category_id' => Category::RECONCILIATION_EXPENSE_ID,
            'account_id' => $this->cash->id, 'type' => 'expense', 'amount' => 10, 'date' => now()->subDay()->toDateString(),
        ]);

        $this->assertNull($this->suggest($this->line('ajustement'))['category_id']);
    }

    public function test_another_users_history_is_not_used(): void
    {
        $stranger = User::factory()->create();
        Transaction::factory()->for($stranger)->create([
            'beneficiary' => 'taxi', 'type' => 'expense', 'amount' => 10, 'date' => now()->subDay()->toDateString(),
        ]);

        $this->assertNull($this->suggest($this->line('taxi'))['category_id']);
    }

    public function test_an_identical_entry_already_saved_is_flagged(): void
    {
        $this->past('taxi', $this->transport, $this->cash, daysAgo: 0, amount: 1500);

        $row = $this->suggest($this->line('Taxi', 1500));

        $this->assertTrue($row['possible_duplicate']);
        $this->assertFalse($this->suggest($this->line('Taxi', 2000))['possible_duplicate']);
    }

    public function test_unparsed_lines_pass_through_untouched(): void
    {
        $row = app(EntrySuggestionService::class)->preview($this->user, [
            new ParsedNoteLine(4, 'Tontine Zara', null, null, null, null, 'unparsed'),
        ], $this->cash)[0];

        $this->assertSame(['unparsed', null, null, 'Tontine Zara'], [$row['status'], $row['category_id'], $row['account_id'], $row['raw']]);
    }
}
