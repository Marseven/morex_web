<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParsedNoteLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lit les notes de dépenses telles que Richard les écrit (module saisie-lot, RM-1 à RM-3).
 *
 *   Vendredi 16 oct            ← en-tête de jour : fixe la date
 *   1500 taxi                  ← montant + libellé
 *   25000+7000 night club      ← une opération par montant
 *   Entrée :                   ← les lignes suivantes sont des revenus
 *   150000 Cyrus - 11/09       ← une date en fin de ligne l'emporte
 *
 * Analyse ligne à ligne avec des motifs ancrés : pas de retour arrière coûteux.
 */
final class NotesParser
{
    private const AMOUNT = '\d+(?:[.,]\d+)?\s*[kKmM]?(?![a-zA-Z])';

    /** Préfixes de mois, du plus spécifique au plus court. */
    private const MONTHS = [
        'janv' => 1, 'jan' => 1, 'fev' => 2, 'feb' => 2, 'mars' => 3, 'mar' => 3, 'avr' => 4, 'apr' => 4,
        'mai' => 5, 'juin' => 6, 'juil' => 7, 'jul' => 7, 'aou' => 8, 'aug' => 8, 'sep' => 9,
        'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /** @return list<ParsedNoteLine> */
    public function parse(string $notes, CarbonImmutable $reference): array
    {
        $date = $reference->startOfDay();
        $month = null;
        $type = 'expense';
        $result = [];

        foreach (preg_split('/\R/u', $notes) ?: [] as $index => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            $plain = Str::lower(Str::ascii($line));

            if ($header = $this->dayHeader($plain, $reference, $month)) {
                [$date, $month] = $header;
                $type = 'expense';

                continue;
            }
            if (preg_match('/^entrees?\s*:?$/', $plain)) {
                $type = 'income';

                continue;
            }
            if (preg_match('/^(depenses?|sorties?)\s*:?$/', $plain)) {
                $type = 'expense';

                continue;
            }

            array_push($result, ...$this->operations($index + 1, $line, $date, $type, $reference));
        }

        return $result;
    }

    /** @return array{CarbonImmutable, int}|null */
    private function dayHeader(string $plain, CarbonImmutable $reference, ?int $currentMonth): ?array
    {
        if (! preg_match('/^(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\s+(\d{1,2})(?:\s+([a-z]+)\.?)?(?:\s+(\d{4}))?\s*:?$/', $plain, $m)) {
            return null;
        }

        $month = isset($m[2]) && $m[2] !== '' ? $this->month($m[2]) : ($currentMonth ?? $reference->month);
        if ($month === null) {
            return null;
        }

        $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        $date = $this->safeDate($year ?? $reference->year, $month, (int) $m[1]);
        if ($date === null) {
            return null;
        }

        return [$year === null ? $this->closestYear($date, $reference) : $date, $month];
    }

    /** @return list<ParsedNoteLine> */
    private function operations(int $lineNumber, string $line, CarbonImmutable $date, string $type, CarbonImmutable $reference): array
    {
        $unparsed = [new ParsedNoteLine($lineNumber, $line, null, null, null, null, 'unparsed')];

        $body = $line;
        if (preg_match('#\s*[-–]\s*(\d{1,2})/(\d{1,2})(?:/(\d{2,4}))?\s*$#u', $body, $d, PREG_OFFSET_CAPTURE)) {
            $year = isset($d[3]) ? (int) $d[3][0] : null;
            $inline = $this->safeDate($year === null ? $reference->year : ($year < 100 ? 2000 + $year : $year), (int) $d[2][0], (int) $d[1][0]);
            if ($inline !== null) {
                $date = $year === null ? $this->closestYear($inline, $reference) : $inline;
                $body = substr($body, 0, $d[0][1]);
            }
        }

        if (! preg_match('/^(?<amounts>'.self::AMOUNT.'(?:\s*\+\s*'.self::AMOUNT.')*)(?<label>.*)$/u', $body, $m)) {
            return $unparsed;
        }

        $amounts = [];
        foreach (preg_split('/\s*\+\s*/', trim($m['amounts'])) ?: [] as $token) {
            $amount = $this->amount($token);
            if ($amount === null) {
                return $unparsed;
            }
            $amounts[] = $amount;
        }

        $label = trim($m['label'], " \t-–:");
        $label = $label === '' ? null : $label;

        return array_map(
            fn (int $amount) => new ParsedNoteLine($lineNumber, $line, $date->toDateString(), $type, $amount, $label, $label === null ? 'incomplete' : 'ok'),
            $amounts,
        );
    }

    private function amount(string $token): ?int
    {
        if (! preg_match('/^(\d+(?:[.,]\d+)?)\s*([kKmM]?)$/', $token, $m)) {
            return null;
        }

        $multiplier = match (strtolower($m[2])) {
            'k' => 1_000,
            'm' => 1_000_000,
            default => 1,
        };
        $value = (float) str_replace(',', '.', $m[1]) * $multiplier;
        $rounded = (int) round($value);

        return $rounded > 0 && abs($value - $rounded) < 1e-6 ? $rounded : null;
    }

    private function month(string $word): ?int
    {
        foreach (self::MONTHS as $prefix => $number) {
            if (str_starts_with($word, $prefix)) {
                return $number;
            }
        }

        return null;
    }

    private function safeDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day)->startOfDay() : null;
    }

    /** RM-2 : sans année, une date à plus de 2 mois dans le futur appartient à l'année précédente. */
    private function closestYear(CarbonImmutable $date, CarbonImmutable $reference): CarbonImmutable
    {
        return $date->greaterThan($reference->addMonths(2)) ? $date->subYear() : $date;
    }
}
