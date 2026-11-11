<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StatementEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lit un relevé collé (module releves-sms) :
 * - CSV bancaire avec en-tête (date, libellé, débit/crédit ou montant signé, frais, référence) ;
 * - sinon, messages Airtel Money / BGFI collés, séparés par une ligne vide, lus par SmsParser.
 */
final class StatementParser
{
    private const HEADERS = [
        'date' => ['date', 'date operation', 'date valeur', 'jour'],
        'label' => ['libelle', 'description', 'operation', 'details', 'motif'],
        'debit' => ['debit', 'sortie', 'retrait'],
        'credit' => ['credit', 'entree', 'depot'],
        'amount' => ['montant', 'amount'],
        'fee' => ['frais', 'fee', 'fees', 'commission'],
        'reference' => ['reference', 'ref', 'tid', 'id transaction'],
    ];

    public function __construct(private readonly SmsParser $sms) {}

    /** @return list<StatementEntry> */
    public function parse(string $text, string $accountName, CarbonImmutable $reference): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') {
            return [];
        }

        $firstLine = strtok($text, "\n");

        return $this->csvColumns($firstLine) !== null
            ? $this->parseCsv($text)
            : $this->parseMessages($text, $accountName, $reference);
    }

    /** @return list<StatementEntry> */
    private function parseMessages(string $text, string $accountName, CarbonImmutable $reference): array
    {
        $blocks = preg_split('/\n\s*\n/', $text) ?: [];
        $entries = [];

        foreach ($blocks as $i => $block) {
            $block = trim($block);
            $parsed = $this->sms->parse($block, $accountName);
            if ($parsed === null || ($parsed['amount'] ?? 0) <= 0) {
                $entries[] = StatementEntry::unparsed($i + 1, $block);

                continue;
            }

            $date = $this->dateIn($block) ?? $reference->toDateString();
            $fee = preg_match('/Frais\s*[:=]?\s*(\d[\d\s]*)\s*(?:FCFA|F)?/i', $block, $f) ? (int) preg_replace('/\D/', '', $f[1]) : 0;
            $amount = (int) $parsed['amount'];

            $entries[] = new StatementEntry(
                $i + 1,
                $date,
                $parsed['is_income'] ? $amount : -$amount,
                $fee,
                $parsed['beneficiary'] ? trim((string) $parsed['beneficiary']) : null,
                $parsed['reference'] ?? null,
                'ok',
                '',
            );
        }

        return $entries;
    }

    /** @return list<StatementEntry> */
    private function parseCsv(string $text): array
    {
        $lines = explode("\n", $text);
        [$delimiter, $columns] = $this->csvColumns(array_shift($lines));
        $entries = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $get = fn (string $key) => isset($columns[$key]) ? trim((string) ($cells[$columns[$key]] ?? '')) : '';

            $date = $this->csvDate($get('date'));
            $amount = $this->signedAmount($get('amount'), $get('debit'), $get('credit'));
            if ($date === null || $amount === null || $amount === 0) {
                $entries[] = StatementEntry::unparsed($i + 2, $line);

                continue;
            }

            $entries[] = new StatementEntry(
                $i + 2,
                $date,
                $amount,
                abs($this->money($get('fee')) ?? 0),
                $get('label') !== '' ? $get('label') : null,
                $get('reference') !== '' ? $get('reference') : null,
                'ok',
                '',
            );
        }

        return $entries;
    }

    /** @return array{string, array<string, int>}|null séparateur + colonnes reconnues */
    private function csvColumns(string $header): ?array
    {
        foreach ([';', "\t", ','] as $delimiter) {
            $columns = [];
            foreach (str_getcsv($header, $delimiter) as $index => $name) {
                $plain = (string) Str::of($name)->ascii()->lower()->squish();
                foreach (self::HEADERS as $key => $aliases) {
                    if (! isset($columns[$key]) && in_array($plain, $aliases, true)) {
                        $columns[$key] = $index;
                    }
                }
            }
            $hasAmount = isset($columns['amount']) || isset($columns['debit']) || isset($columns['credit']);
            if (isset($columns['date']) && $hasAmount) {
                return [$delimiter, $columns];
            }
        }

        return null;
    }

    private function signedAmount(string $amount, string $debit, string $credit): ?int
    {
        if ($amount !== '') {
            return $this->money($amount);
        }
        $out = $this->money($debit);
        $in = $this->money($credit);

        return match (true) {
            $out !== null && $out !== 0 => -abs($out),
            $in !== null && $in !== 0 => abs($in),
            default => null,
        };
    }

    /** « 1 000 000,00 », « 1.000 », « -15500 » → entier FCFA (les centimes éventuels sont ignorés). */
    private function money(string $value): ?int
    {
        $value = preg_replace('/[\s\x{00A0}\x{202F}]|FCFA|XAF|F$/u', '', $value) ?? '';
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/[.,]\d{1,2}$/', '', $value) ?? '';   // décimales de centimes
        $value = str_replace(['.', ','], '', $value);                   // séparateurs de milliers

        return preg_match('/^-?\d{1,13}$/', $value) ? (int) $value : null;
    }

    private function csvDate(string $value): ?string
    {
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue; // Carbon 3 lève une exception quand le format ne correspond pas
            }
            if ($date !== null && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function dateIn(string $block): ?string
    {
        if (! preg_match('#\b(\d{2})[/-](\d{2})[/-](\d{4}|\d{2})\b#', $block, $m)) {
            return null;
        }
        $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

        return checkdate((int) $m[2], (int) $m[1], $year) ? sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]) : null;
    }
}
