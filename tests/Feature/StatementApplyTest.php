<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module releves-sms (3a) : aperçu et validation (RM-2 frais, RM-4, RM-5 atomicité) via l'API et le web.
 */
class StatementApplyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $airtel;

    private Category $outings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->airtel = Account::factory()->for($this->user)->withBalance(500000)->create(['name' => 'Compte Airtel Money']);
        $this->outings = Category::factory()->create(['user_id' => $this->user->id, 'type' => 'expense', 'name' => 'Sorties']);
    }

    private function note(int $amount, string $date = '2026-10-24'): Transaction
    {
        return Transaction::factory()->for($this->user)->create([
            'account_id' => $this->airtel->id, 'type' => 'expense', 'amount' => $amount, 'date' => $date, 'category_id' => $this->outings->id,
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/accounts/{$this->airtel->id}/statement{$suffix}";
    }

    public function test_preview_reads_and_matches_without_saving(): void
    {
        $note = $this->note(15500);
        Sanctum::actingAs($this->user);

        $this->postJson($this->url('/preview'), [
            'text' => "24/10/2026 21:10\nPaiement de 15500F DIGITECH pour repas. Frais 547F. TID: CI241024.2110.B22222",
        ])->assertOk()
            ->assertJsonPath('data.lines.0.status', 'matched')
            ->assertJsonPath('data.lines.0.transaction_id', $note->id)
            ->assertJsonPath('data.lines.0.fee', 547);

        $this->assertDatabaseCount('statement_lines', 0);
    }

    public function test_validating_a_match_creates_the_fee_and_points_the_operation(): void
    {
        $note = $this->note(15500);
        $before = $this->airtel->refresh()->balance;
        Sanctum::actingAs($this->user);

        $this->postJson($this->url(), ['lines' => [[
            'date' => '2026-10-24', 'amount' => -15500, 'fee' => 547, 'label' => 'DIGITECH', 'reference' => 'CI241024.2110.B22222',
            'action' => 'match', 'transaction_id' => $note->id,
        ]]])->assertOk()
            ->assertJsonPath('data.matched', 1)
            ->assertJsonPath('data.fees_total', 547);

        $line = StatementLine::sole();
        $fee = Transaction::find($line->fee_transaction_id);
        $this->assertSame(['matched', $note->id], [$line->status, $line->transaction_id]);
        $this->assertSame([547, 'expense', Category::BANK_FEES_ID], [$fee->amount, $fee->type, $fee->category_id]);
        $this->assertNotNull($note->refresh()->reconciled_at);
        $this->assertSame($before - 547, $this->airtel->refresh()->balance);
    }

    public function test_creating_a_missing_operation_moves_the_balance_once(): void
    {
        Sanctum::actingAs($this->user);
        $before = $this->airtel->balance;

        $this->postJson($this->url(), ['lines' => [[
            'date' => '2026-10-18', 'amount' => -35855, 'fee' => 0, 'label' => 'BAMBOO PAY', 'reference' => null,
            'action' => 'create', 'category_id' => $this->outings->id,
        ]]])->assertOk()->assertJsonPath('data.created', 1);

        $created = Transaction::where('beneficiary', 'BAMBOO PAY')->sole();
        $this->assertSame([35855, 'expense', $this->outings->id], [$created->amount, $created->type, $created->category_id]);
        $this->assertSame($before - 35855, $this->airtel->refresh()->balance);
    }

    public function test_ignored_lines_are_remembered_for_the_next_import(): void
    {
        Sanctum::actingAs($this->user);
        $line = ['date' => '2026-10-20', 'amount' => -5000, 'fee' => 0, 'label' => 'X', 'reference' => 'CI241020.1000.A00009', 'action' => 'ignore'];

        $this->postJson($this->url(), ['lines' => [$line]])->assertOk();

        $this->assertSame('ignored', StatementLine::sole()->status);
        $this->postJson($this->url('/preview'), ['text' => 'Vous avez envoye 5000F au 074000000 X. Frais 0F. TID: CI241020.1000.A00009'])
            ->assertJsonPath('data.lines.0.status', 'already_imported');
    }

    public function test_a_transaction_of_another_account_is_refused_and_nothing_is_saved(): void
    {
        $other = Account::factory()->for($this->user)->create();
        $foreign = Transaction::factory()->for($this->user)->create(['account_id' => $other->id, 'type' => 'expense', 'amount' => 100, 'date' => '2026-10-24']);
        Sanctum::actingAs($this->user);

        $this->postJson($this->url(), ['lines' => [
            ['date' => '2026-10-24', 'amount' => -5000, 'fee' => 0, 'label' => 'A', 'reference' => 'R1', 'action' => 'ignore'],
            ['date' => '2026-10-24', 'amount' => -100, 'fee' => 50, 'label' => 'B', 'reference' => 'R2', 'action' => 'match', 'transaction_id' => $foreign->id],
        ]])->assertUnprocessable();

        $this->assertDatabaseCount('statement_lines', 0);
        $this->assertNull($foreign->refresh()->reconciled_at);
    }

    public function test_an_operation_already_matched_cannot_be_matched_again(): void
    {
        $note = $this->note(2000);
        Sanctum::actingAs($this->user);
        $line = fn (string $ref) => ['date' => '2026-10-24', 'amount' => -2000, 'fee' => 0, 'label' => 'T', 'reference' => $ref, 'action' => 'match', 'transaction_id' => $note->id];

        $this->postJson($this->url(), ['lines' => [$line('R1')]])->assertOk();
        $this->postJson($this->url(), ['lines' => [$line('R2')]])->assertUnprocessable()->assertJsonValidationErrors('lines');
    }

    public function test_someone_else_account_is_forbidden_and_guests_unauthorised(): void
    {
        $this->postJson($this->url('/preview'), ['text' => 'x'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->url('/preview'), ['text' => 'x'])->assertForbidden();
        $this->postJson($this->url(), ['lines' => []])->assertForbidden();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidLines(): array
    {
        $base = ['date' => '2026-10-24', 'amount' => -100, 'fee' => 0, 'label' => 'A', 'reference' => null, 'action' => 'ignore'];

        return [
            'action inconnue' => [['action' => 'delete'] + $base],
            'montant nul' => [['amount' => 0] + $base],
            'frais négatifs' => [['fee' => -5] + $base],
            'création sans catégorie' => [['action' => 'create'] + $base],
            "création en catégorie d'écart" => [['action' => 'create', 'category_id' => Category::RECONCILIATION_EXPENSE_ID] + $base],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLines')]
    public function test_invalid_lines_get_422(array $line): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson($this->url(), ['lines' => [$line]])->assertUnprocessable();
    }

    public function test_the_web_preview_works_with_the_session(): void
    {
        $this->actingAs($this->user)
            ->postJson("/accounts/{$this->airtel->id}/statement/preview", ['text' => 'RETRAIT de 1000F reussi vers AG1. TID: CI1'])
            ->assertOk()
            ->assertJsonPath('data.lines.0.amount', -1000);
    }
}
