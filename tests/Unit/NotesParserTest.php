<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\ParsedNoteLine;
use App\Services\NotesParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Module saisie-lot : lecture des notes telles que Richard les écrit (RM-1 à RM-3).
 */
class NotesParserTest extends TestCase
{
    private NotesParser $parser;

    private CarbonImmutable $reference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new NotesParser;
        $this->reference = CarbonImmutable::parse('2026-10-27');
    }

    /** @return list<ParsedNoteLine> */
    private function parse(string $notes): array
    {
        return $this->parser->parse($notes, $this->reference);
    }

    /** @return list<array{string|null, string|null, int|null, string|null, string}> */
    private function summary(string $notes): array
    {
        return array_map(
            fn (ParsedNoteLine $l) => [$l->date, $l->type, $l->amount, $l->label, $l->status],
            $this->parse($notes),
        );
    }

    public function test_a_full_day_with_sums_and_an_income_section(): void
    {
        $notes = <<<'TXT'
        Vendredi 16 oct
        1500 taxi
        25000+7000+12000 night club
        Entrée :
        110000 - Freelance
        TXT;

        $this->assertSame([
            ['2026-10-16', 'expense', 1500, 'taxi', 'ok'],
            ['2026-10-16', 'expense', 25000, 'night club', 'ok'],
            ['2026-10-16', 'expense', 7000, 'night club', 'ok'],
            ['2026-10-16', 'expense', 12000, 'night club', 'ok'],
            ['2026-10-16', 'income', 110000, 'Freelance', 'ok'],
        ], $this->summary($notes));
    }

    public function test_a_new_day_header_ends_the_income_section(): void
    {
        $lines = $this->summary("Samedi 17 octobre\nEntree :\n90000 vente\nDimanche 18 octobre\n2000 offrande église");

        $this->assertSame(['income', 'expense'], array_column($lines, 1));
        $this->assertSame(['2026-10-17', '2026-10-18'], array_column($lines, 0));
    }

    public function test_a_header_without_month_keeps_the_previous_month(): void
    {
        $lines = $this->summary("Mardi 29 sept\n1000 taxi\nJeudi 1 oct\n4000 achat Emilie\nLundi 19\n1000 taxi");

        $this->assertSame(['2026-09-29', '2026-10-01', '2026-10-19'], array_column($lines, 0));
    }

    public function test_a_header_without_any_month_uses_the_reference_month(): void
    {
        $this->assertSame('2026-10-20', $this->parse("Mardi 20\n2000 taxi")[0]->date);
    }

    public function test_lines_before_any_header_use_the_reference_date(): void
    {
        $this->assertSame('2026-10-27', $this->parse('3500 repas')[0]->date);
    }

    public function test_an_inline_date_wins_over_the_header(): void
    {
        $lines = $this->summary("Entrée\n150000 Cyrus - 11/09\n250000 Mbira - 02/09");

        $this->assertSame([
            ['2026-09-11', 'income', 150000, 'Cyrus', 'ok'],
            ['2026-09-02', 'income', 250000, 'Mbira', 'ok'],
        ], $lines);
    }

    /** @return array<string, array{string, list<int>}> */
    public static function amounts(): array
    {
        return [
            'k' => ['205k tontine', [205000]],
            'k répété' => ['205k + 205k + 205k tontine', [205000, 205000, 205000]],
            'M décimal' => ['1.5M ordinateur', [1500000]],
            'k à virgule' => ['2,5k café', [2500]],
            'espaces autour du +' => ['500+1000 + 1500 taxi', [500, 1000, 1500]],
        ];
    }

    /** @param list<int> $expected */
    #[DataProvider('amounts')]
    public function test_amount_notations(string $line, array $expected): void
    {
        $this->assertSame($expected, array_map(fn ($l) => $l->amount, $this->parse($line)));
    }

    public function test_an_unreadable_line_is_kept_and_flagged(): void
    {
        $lines = $this->parse("Tontine Zara\n1500 taxi");

        $this->assertSame('unparsed', $lines[0]->status);
        $this->assertSame('Tontine Zara', $lines[0]->raw);
        $this->assertNull($lines[0]->amount);
        $this->assertSame('ok', $lines[1]->status);
    }

    public function test_an_amount_without_label_needs_completion(): void
    {
        $line = $this->parse('20000')[0];

        $this->assertSame([20000, null, 'incomplete'], [$line->amount, $line->label, $line->status]);
    }

    public function test_a_date_far_in_the_future_falls_back_to_last_year(): void
    {
        $this->assertSame('2025-12-30', $this->parse("Mardi 30 déc\n2000 taxi")[0]->date);
    }

    public function test_blank_lines_are_ignored_and_line_numbers_kept(): void
    {
        $lines = $this->parse("\n1500 taxi\n\n2000 repas\n");

        $this->assertSame([2, 4], array_map(fn ($l) => $l->lineNumber, $lines));
    }

    public function test_a_zero_amount_is_not_accepted(): void
    {
        $this->assertSame('unparsed', $this->parse('0 taxi')[0]->status);
    }

    // --- Revue de code : lectures silencieusement fausses ---

    /** @return array<string, array{string}> */
    public static function glued(): array
    {
        return [
            'lettre collée' => ['2000F taxi'],
            'libellé collé' => ['1500taxi'],
            'décimale sans suffixe' => ['2.5 café'],
        ];
    }

    #[DataProvider('glued')]
    public function test_an_ambiguous_amount_is_flagged_not_misread(string $line): void
    {
        $this->assertSame('unparsed', $this->parse($line)[0]->status);
    }

    /** @return array<string, array{string, int}> */
    public static function thousands(): array
    {
        return [
            'espace' => ['1 500 taxi', 1500],
            'point' => ['1.000 taxi', 1000],
            'virgule' => ['1,000 taxi', 1000],
            'grand nombre' => ['1 000 000 ordi avance', 1000000],
        ];
    }

    #[DataProvider('thousands')]
    public function test_thousand_separators_are_understood(string $line, int $amount): void
    {
        $parsed = $this->parse($line)[0];

        $this->assertSame([$amount, 'ok'], [$parsed->amount, $parsed->status]);
    }

    public function test_a_unit_letter_separated_by_a_space_stays_in_the_label(): void
    {
        $parsed = $this->parse('1500 m taxi')[0];

        $this->assertSame([1500, 'm taxi'], [$parsed->amount, $parsed->label]);
    }

    public function test_a_header_without_month_moves_to_the_next_month_and_year(): void
    {
        $parser = new NotesParser;
        $lines = $parser->parse("Jeudi 31 dec\n2000 taxi\nVendredi 1\n1000 taxi", CarbonImmutable::parse('2027-01-05'));

        $this->assertSame(['2026-12-31', '2027-01-01'], array_map(fn ($l) => $l->date, $lines));
    }

    public function test_amounts_above_the_ceiling_are_refused(): void
    {
        $this->assertSame('unparsed', $this->parse('99999999999999999999 taxi')[0]->status);
        $this->assertSame('unparsed', $this->parse('2000000M taxi')[0]->status);
    }

    public function test_a_date_written_as_a_line_is_not_an_amount(): void
    {
        $this->assertSame('unparsed', $this->parse('16 oct')[0]->status);
    }

    public function test_an_inline_date_without_dash_is_understood(): void
    {
        $parsed = $this->parse('2000 taxi 12/10')[0];

        $this->assertSame(['2026-10-12', 'taxi'], [$parsed->date, $parsed->label]);
    }

    public function test_an_impossible_inline_date_is_flagged(): void
    {
        $this->assertSame('unparsed', $this->parse('2000 taxi - 31/02')[0]->status);
    }
}
