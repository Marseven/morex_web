<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\StatementEntry;
use App\Services\SmsParser;
use App\Services\StatementParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Module releves-sms (3a) : lecture des relevés collés (SMS Airtel) ou exportés (CSV bancaire).
 */
class StatementParserTest extends TestCase
{
    private StatementParser $parser;

    private CarbonImmutable $reference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StatementParser(new SmsParser);
        $this->reference = CarbonImmutable::parse('2026-10-27');
    }

    /** @return list<StatementEntry> */
    private function parse(string $text, string $accountName = 'Compte Airtel Money'): array
    {
        return $this->parser->parse($text, $accountName, $this->reference);
    }

    public function test_pasted_airtel_sms_become_signed_lines_with_fees_and_references(): void
    {
        $text = <<<'TXT'
        18/10/2026 18:51
        Vous avez envoye 10000F au 074000000 MEBODO. Frais 450F. TID: CI241018.1851.A12345

        24/10/2026 21:10
        Paiement de 15500F DIGITECH pour restaurant. Frais 547F. TID: CI241024.2110.B22222

        22/10/2026
        Vous avez recu 71500F du 077000000 , PROJET CLIENT. TID: CI241022.0930.C33333
        TXT;

        $lines = $this->parse($text);

        $this->assertSame(
            [['2026-10-18', -10000, 450, 'MEBODO', 'CI241018.1851.A12345', 'ok'],
                ['2026-10-24', -15500, 547, 'DIGITECH', 'CI241024.2110.B22222', 'ok'],
                ['2026-10-22', 71500, 0, 'PROJET CLIENT', 'CI241022.0930.C33333', 'ok']],
            array_map(fn (StatementEntry $e) => [$e->date, $e->amount, $e->fee, $e->label, $e->reference, $e->status], $lines),
        );
    }

    public function test_an_sms_without_date_uses_the_reference_date(): void
    {
        $line = $this->parse('RETRAIT de 300000F reussi vers AGENT01. TID: CI241017.1800.D44444')[0];

        $this->assertSame(['2026-10-27', -300000], [$line->date, $line->amount]);
    }

    public function test_an_unknown_message_is_flagged(): void
    {
        $line = $this->parse('Promo : 1 Go offert ce week-end !')[0];

        $this->assertSame('unparsed', $line->status);
        $this->assertNull($line->amount);
    }

    public function test_a_bank_csv_with_debit_and_credit_columns(): void
    {
        $csv = <<<'CSV'
        Date;Libellé;Débit;Crédit;Référence
        29/09/2026;RETRAIT CHEQUE;1 000 000,00;;CHQ001
        30/09/2026;AGIOS DEBITEURS;1 448,00;;
        29/09/2026;VIREMENT RECU;;700 000,00;VIR778
        CSV;

        $lines = $this->parse($csv, 'Compte Courant');

        $this->assertSame(
            [['2026-09-29', -1000000, 'RETRAIT CHEQUE', 'CHQ001'],
                ['2026-09-30', -1448, 'AGIOS DEBITEURS', null],
                ['2026-09-29', 700000, 'VIREMENT RECU', 'VIR778']],
            array_map(fn (StatementEntry $e) => [$e->date, $e->amount, $e->label, $e->reference], $lines),
        );
    }

    public function test_a_csv_with_a_signed_amount_column_and_iso_dates(): void
    {
        $csv = "date,description,montant\n2026-10-16,Hostinger,-102251\n2026-10-17,Remboursement,72100";

        $this->assertSame([-102251, 72100], array_map(fn ($e) => $e->amount, $this->parse($csv, 'Compte Bgfi Mobile')));
    }

    public function test_a_csv_row_without_valid_amount_or_date_is_flagged(): void
    {
        $lines = $this->parse("Date;Libellé;Débit;Crédit\n31/02/2026;X;100;\n01/10/2026;Y;;", 'Compte Courant');

        $this->assertSame(['unparsed', 'unparsed'], array_map(fn ($e) => $e->status, $lines));
    }
}
