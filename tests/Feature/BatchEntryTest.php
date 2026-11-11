<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module saisie-lot : aperçu (API et web) et enregistrement via l'import étendu.
 */
class BatchEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cash;

    private Account $airtel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cash = Account::factory()->for($this->user)->withBalance(100000)->create(['name' => 'Portefeuille', 'is_default' => true]);
        $this->airtel = Account::factory()->for($this->user)->withBalance(50000)->create(['name' => 'Airtel']);
    }

    private function payload(array $override = []): array
    {
        return $override + [
            'notes' => "Vendredi 16 oct\n1500 taxi\n2000+3000 night club\nEntrée :\n110000 - Freelance\nTontine Zara",
            'reference_date' => '2026-10-27',
            'default_account_id' => $this->cash->id,
        ];
    }

    public function test_api_preview_returns_the_parsed_lines_without_saving(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/transactions/notes/preview', $this->payload())->assertOk();

        $response->assertJsonCount(5, 'data.lines')
            ->assertJsonPath('data.lines.0.amount', 1500)
            ->assertJsonPath('data.lines.0.date', '2026-10-16')
            ->assertJsonPath('data.lines.0.account_id', $this->cash->id)
            ->assertJsonPath('data.lines.3.type', 'income')
            ->assertJsonPath('data.lines.4.status', 'unparsed')
            ->assertJsonPath('data.totals.expense', 6500)
            ->assertJsonPath('data.totals.income', 110000);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_api_preview_requires_authentication(): void
    {
        $this->postJson('/api/transactions/notes/preview', $this->payload())->assertUnauthorized();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidPreviews(): array
    {
        return [
            'notes vides' => [['notes' => '']],
            'texte trop long' => [['notes' => str_repeat('x', 20001)]],
            'trop de lignes' => [['notes' => str_repeat("1 a\n", 301)]],
            'date invalide' => [['reference_date' => '27/10/2026']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPreviews')]
    public function test_invalid_previews_get_422(array $override): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/transactions/notes/preview', $this->payload($override))->assertUnprocessable();
    }

    public function test_preview_refuses_another_users_default_account(): void
    {
        Sanctum::actingAs($this->user);
        $foreign = Account::factory()->for(User::factory())->create();

        $this->postJson('/api/transactions/notes/preview', $this->payload(['default_account_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_account_id');
    }

    public function test_web_page_and_preview_work_with_the_session(): void
    {
        $this->actingAs($this->user)->get('/transactions/batch')->assertOk();

        $this->actingAs($this->user)
            ->postJson('/transactions/notes/preview', $this->payload())
            ->assertOk()
            ->assertJsonCount(5, 'data.lines');
    }

    public function test_import_accepts_one_account_per_row(): void
    {
        $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);

        $this->actingAs($this->user)->post('/transactions/import', [
            'account_id' => $this->cash->id,
            'rows' => [
                ['date' => '2026-10-16', 'amount' => 1500, 'type' => 'expense', 'beneficiary' => 'taxi', 'category_id' => $category->id],
                ['date' => '2026-10-16', 'amount' => 2000, 'type' => 'expense', 'beneficiary' => 'night club', 'category_id' => $category->id, 'account_id' => $this->airtel->id],
            ],
        ])->assertRedirect();

        $this->assertSame(98500, $this->cash->refresh()->balance);
        $this->assertSame(48000, $this->airtel->refresh()->balance);
    }

    public function test_import_refuses_a_row_on_another_users_account(): void
    {
        $foreign = Account::factory()->for(User::factory())->withBalance(1000)->create();

        $this->actingAs($this->user)->post('/transactions/import', [
            'account_id' => $this->cash->id,
            'rows' => [['date' => '2026-10-16', 'amount' => 500, 'type' => 'expense', 'account_id' => $foreign->id]],
        ])->assertSessionHasErrors('rows.0.account_id');

        $this->assertSame(1000, $foreign->refresh()->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_import_refuses_reconciliation_gap_categories(): void
    {
        $this->actingAs($this->user)->post('/transactions/import', [
            'account_id' => $this->cash->id,
            'rows' => [['date' => '2026-10-16', 'amount' => 500, 'type' => 'expense', 'category_id' => Category::RECONCILIATION_EXPENSE_ID]],
        ])->assertSessionHasErrors('rows.0.category_id');
    }

    public function test_import_refuses_absurd_amounts(): void
    {
        $this->actingAs($this->user)->post('/transactions/import', [
            'account_id' => $this->cash->id,
            'rows' => [['date' => '2026-10-16', 'amount' => PHP_INT_MAX, 'type' => 'expense']],
        ])->assertSessionHasErrors('rows.0.amount');
    }
}
