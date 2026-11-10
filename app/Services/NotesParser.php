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
    /**
     * Un montant : groupé par milliers (« 1 500 », « 1.000 ») ou simple, décimale permise seulement
     * avec suffixe (« 1.5M », « 2,5k »), et toujours suivi d'un séparateur : « 2000F » n'est pas un montant.
     */
    private const AMOUNT = '(?:\d{1,3}(?:[ .,]\d{3})+|\d+(?:[.,]\d+)?)[kKmM]?(?=$|[\s+:\x{2013}-])';

    /** Plafond de sécurité (cohérent avec la validation de l'import). */
    public const MAX_AMOUNT = 1_000_000_000_000;

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
        $lastHeader = null;
        $type = 'expense';
        $result = [];

        foreach (preg_split('/\R/u', $notes) ?: [] as $index => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            $plain = Str::lower(Str::ascii($line));

            if ($header = $this->dayHeader($plain, $reference, $lastHeader)) {
                $date = $lastHeader = $header;
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

    /**
     * En-tête de jour. Sans mois, on reprend celui de l'en-tête précédent, et on passe au mois
     * suivant (année comprise) quand le jour recule : « Jeudi 31 déc » puis « Vendredi 1 » → 1er janvier.
     */
    private function dayHeader(string $plain, CarbonImmutable $reference, ?CarbonImmutable $last): ?CarbonImmutable
    {
        if (! preg_match('/^(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\s+(\d{1,2})(?:\s+([a-z]+)\.?)?(?:\s+(\d{4}))?\s*:?$/', $plain, $m)) {
            return null;
        }
        $day = (int) $m[1];
        $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;

        if (isset($m[2]) && $m[2] !== '') {
            $month = $this->month($m[2]);
            $date = $month === null ? null : $this->safeDate($year ?? $reference->year, $month, $day);

            return $date === null || $year !== null ? $date : $this->closestYear($date, $reference);
        }

        if ($last !== null) {
            $base = $day < $last->day ? $last->startOfMonth()->addMonth() : $last;

            return $this->safeDate($base->year, $base->month, $day);
        }

        $date = $this->safeDate($reference->year, $reference->month, $day);

        return $date === null ? null : $this->closestYear($date, $reference);
    }

    /** @return list<ParsedNoteLine> */
    private function operations(int $lineNumber, string $line, CarbonImmutable $date, string $type, CarbonImmutable $reference): array
    {
        $unparsed = [new ParsedNoteLine($lineNumber, $line, null, null, null, null, 'unparsed')];

        // Date en fin de ligne, avec ou sans tiret : « - 11/09 », « 12/10 »
        $body = $line;
        if (preg_match('#(?:\s*[-\x{2013}]\s*|\s+)(\d{1,2})/(\d{1,2})(?:/(\d{2,4}))?\s*$#u', $body, $d, PREG_OFFSET_CAPTURE)) {
            $year = isset($d[3]) ? (int) $d[3][0] : null;
            $inline = $this->safeDate($year === null ? $reference->year : ($year < 100 ? 2000 + $year : $year), (int) $d[2][0], (int) $d[1][0]);
            if ($inline === null) {
                return $unparsed; // « 31/02 » : signalé plutôt qu'ignoré
            }
            $date = $year === null ? $this->closestYear($inline, $reference) : $inline;
            $body = substr($body, 0, $d[0][1]);
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

        $label = trim($m['label'], " \t-\u{2013}:");
        $label = $label === '' ? null : $label;

        // « 16 oct » est une date oubliée, pas 16 FCFA d'« oct »
        if ($label !== null && preg_match('/^[a-z]+\.?$/', $plainLabel = Str::lower(Str::ascii($label))) && $this->month($plainLabel) !== null) {
            return $unparsed;
        }

        return array_map(
            fn (int $amount) => new ParsedNoteLine($lineNumber, $line, $date->toDateString(), $type, $amount, $label, $label === null ? 'incomplete' : 'ok'),
            $amounts,
        );
    }

    private function amount(string $token): ?int
    {
        if (! preg_match('/^([\d .,]+?)([kKmM]?)$/', $token, $m)) {
            return null;
        }
        [, $number, $suffix] = $m;

        if ($suffix === '') {
            // Sans suffixe : entier simple ou groupé par milliers ; « 2.5 » est ambigu, donc refusé
            if (preg_match('/^\d{1,3}(?:[ .,]\d{3})+$/', $number)) {
                $number = preg_replace('/[ .,]/', '', $number);
            }
            if (! preg_match('/^\d{1,13}$/', $number)) {
                return null;
            }
            $value = (int) $number;
        } else {
            $decimal = str_replace([' ', ','], ['', '.'], $number);
            if (! preg_match('/^\d{1,10}(?:\.\d+)?$/', $decimal)) {
                return null;
            }
            $float = (float) $decimal * (strtolower($suffix) === 'k' ? 1_000 : 1_000_000);
            if ($float >= self::MAX_AMOUNT || abs($float - round($float)) > 1e-6) {
                return null;
            }
            $value = (int) round($float);
        }

        return $value > 0 && $value < self::MAX_AMOUNT ? $value : null;
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
