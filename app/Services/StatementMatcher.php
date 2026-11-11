<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParsedNoteLine;
use App\DTOs\StatementEntry;
use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Associe les lignes d'un relevé aux opérations saisies sur le compte (module releves-sms).
 *
 * RM-1 : même compte, même sens, date à ±1 jour ; montant égal, ou relevé = opération + frais
 *        (frais ≤ max(1 000 FCFA, 3 %)). Une seule candidate requise, sinon « à valider ».
 * RM-3 : une opération ne sert qu'à une ligne. Les montants exacts sont servis avant les frais.
 * Rien n'est enregistré ici.
 */
final class StatementMatcher
{
    public const DAY_TOLERANCE = 1;

    public const MIN_FEE_CEILING = 1000;

    public const FEE_RATE = 0.03;

    public function __construct(private readonly EntrySuggestionService $suggestions) {}

    /** RM-1 : frais acceptables pour une opération de ce montant. */
    public static function feeCeiling(int $amount): int
    {
        return max(self::MIN_FEE_CEILING, (int) floor($amount * self::FEE_RATE));
    }

    /**
     * @param  list<StatementEntry>  $entries
     * @return array{lines: list<array<string, mixed>>, missing: list<array<string, mixed>>}
     */
    public function match(Account $account, array $entries): array
    {
        $readable = array_values(array_filter($entries, fn (StatementEntry $e) => $e->status === 'ok'));
        $pool = $this->pool($account, $readable);
        $used = [];
        $rows = [];

        foreach ($entries as $i => $entry) {
            $rows[$i] = $entry->toArray() + ['transaction_id' => null, 'transaction' => null, 'candidates' => [], 'suggested_category_id' => null];
            if ($entry->status !== 'ok') {
                $rows[$i]['status'] = 'unparsed';
            } elseif ($this->alreadyImported($account, $entry)) {
                $rows[$i]['status'] = 'already_imported';
            } else {
                $rows[$i]['status'] = null; // à décider par les deux passes
            }
        }

        // Passe 1 : montants exacts (frais annoncés à part, ou déjà inclus dans la note)
        foreach ($entries as $i => $entry) {
            if ($rows[$i]['status'] !== null) {
                continue;
            }
            $exact = $this->candidates($pool, $entry, $used, fn (Transaction $t) => $t->amount === abs($entry->amount) || ($entry->fee > 0 && $t->amount === abs($entry->amount) + $entry->fee));
            $this->decide($rows[$i], $exact, $used, fn (Transaction $t) => $t->amount === abs($entry->amount) ? $entry->fee : 0);
        }

        // Passe 2 : relevé = opération + frais
        foreach ($entries as $i => $entry) {
            if ($rows[$i]['status'] !== null) {
                continue;
            }
            $withFee = $this->candidates($pool, $entry, $used, function (Transaction $t) use ($entry) {
                $gap = abs($entry->amount) - $t->amount;

                return $gap > 0 && $gap <= self::feeCeiling($t->amount);
            });
            $this->decide($rows[$i], $withFee, $used, fn (Transaction $t) => abs($entry->amount) - $t->amount);

            if ($rows[$i]['status'] === null) {
                $rows[$i]['status'] = 'no_transaction';
                $rows[$i]['suggested_category_id'] = $this->suggestedCategory($account, $entry);
            }
        }

        $offered = collect($rows)->pluck('candidates')->flatten(1)->pluck('id')->all();
        $missing = $pool
            ->reject(fn (Transaction $t) => isset($used[$t->id]) || in_array($t->id, $offered, true))
            ->filter(fn (Transaction $t) => $this->inPeriod($t, $readable))
            ->map(fn (Transaction $t) => $this->summary($t))
            ->values()
            ->all();

        return ['lines' => array_values($rows), 'missing' => $missing];
    }

    /** @param  array<string, mixed>  $row */
    private function decide(array &$row, Collection $candidates, array &$used, callable $fee): void
    {
        if ($candidates->count() === 1) {
            $tx = $candidates->first();
            $used[$tx->id] = true;
            $row['status'] = 'matched';
            $row['transaction_id'] = $tx->id;
            $row['transaction'] = $this->summary($tx);
            $row['fee'] = $fee($tx);
        } elseif ($candidates->count() > 1) {
            $row['status'] = 'to_validate';
            $row['candidates'] = $candidates->map(fn (Transaction $t) => $this->summary($t) + ['fee' => $fee($t)])->values()->all();
        }
    }

    private function candidates(Collection $pool, StatementEntry $entry, array $used, callable $amountRule): Collection
    {
        $type = $entry->amount < 0 ? 'expense' : 'income';
        $date = CarbonImmutable::parse($entry->date);

        return $pool->filter(fn (Transaction $t) => ! isset($used[$t->id])
            && $t->type === $type
            && abs($t->date->diffInDays($date)) <= self::DAY_TOLERANCE
            && $amountRule($t));
    }

    /** Opérations du compte sur la période, pas encore liées à un relevé (ni frais déjà créés). */
    private function pool(Account $account, array $readable): Collection
    {
        if ($readable === []) {
            return collect();
        }
        $dates = array_map(fn (StatementEntry $e) => $e->date, $readable);
        sort($dates);
        $from = CarbonImmutable::parse($dates[0])->subDays(self::DAY_TOLERANCE)->toDateString();
        $to = CarbonImmutable::parse(end($dates))->addDays(self::DAY_TOLERANCE)->toDateString().' 23:59:59';

        $linked = StatementLine::where('account_id', $account->id)->whereNotNull('transaction_id')->pluck('transaction_id')
            ->merge(StatementLine::where('account_id', $account->id)->whereNotNull('fee_transaction_id')->pluck('fee_transaction_id'));

        return Transaction::query()
            ->where('user_id', $account->user_id)
            ->where('account_id', $account->id)
            ->whereIn('type', ['income', 'expense'])
            ->where('status', '!=', Transaction::STATUS_PENDING)
            ->whereNotIn('id', $linked)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();
    }

    private function alreadyImported(Account $account, StatementEntry $entry): bool
    {
        $query = StatementLine::where('account_id', $account->id);

        return $entry->reference !== null
            ? $query->whereIn('external_ref', [$entry->reference, SmsReconciler::REFERENCE_PREFIX.$entry->reference])->exists()
            : $query->whereBetween('date', [$entry->date, $entry->date.' 23:59:59'])
                ->where('amount', $entry->amount)
                ->get(['label'])
                ->contains(fn (StatementLine $l) => Str::lower((string) $l->label) === Str::lower((string) $entry->label));
    }

    private function suggestedCategory(Account $account, StatementEntry $entry): ?string
    {
        if ($entry->label === null) {
            return null;
        }
        $line = new ParsedNoteLine($entry->index, $entry->label, $entry->date, $entry->amount < 0 ? 'expense' : 'income', abs($entry->amount), $entry->label, 'ok');
        $id = $this->suggestions->preview($account->user, [$line], $account)[0]['category_id'] ?? null;

        return in_array($id, [...Category::RECONCILIATION_IDS, Category::BANK_FEES_ID], true) ? null : $id;
    }

    /** @param list<StatementEntry> $readable */
    private function inPeriod(Transaction $t, array $readable): bool
    {
        $dates = array_map(fn (StatementEntry $e) => $e->date, $readable);

        return $t->date->toDateString() >= min($dates) && $t->date->toDateString() <= max($dates);
    }

    /** @return array<string, mixed> */
    private function summary(Transaction $t): array
    {
        return ['id' => $t->id, 'date' => $t->date->toDateString(), 'amount' => $t->amount, 'type' => $t->type, 'label' => $t->beneficiary ?? $t->description];
    }
}
