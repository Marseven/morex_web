<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module releves-sms (3a) : lignes de relevé et catégorie système des frais.
 */
class StatementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_system_fee_category_exists(): void
    {
        $fees = Category::find(Category::BANK_FEES_ID);

        $this->assertSame(['Frais bancaires', 'expense', true, null], [$fees?->name, $fees?->type, $fees?->is_system, $fees?->user_id]);
    }

    public function test_an_external_reference_is_unique_per_account(): void
    {
        $account = Account::factory()->for(User::factory())->create();
        $line = fn () => tap(new StatementLine([
            'account_id' => $account->id, 'date' => '2026-10-24', 'amount' => -16047,
            'label' => 'Digitech Africa', 'external_ref' => 'CI241024.1234.A00001', 'source' => 'paste',
        ]), fn ($l) => $l->forceFill(['user_id' => $account->user_id])->save());

        $line();

        $this->expectException(QueryException::class);
        $line();
    }

    public function test_the_raw_statement_text_is_never_stored(): void
    {
        $this->assertNotContains('raw', (new StatementLine)->getFillable());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('statement_lines', 'raw'));
    }
}
