<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParsedNoteLine;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Complète les lignes de la saisie en lot à partir de l'historique de l'utilisateur (module saisie-lot).
 *
 * RM-4 : catégorie et compte les plus utilisés sur 180 jours avec le même libellé normalisé ;
 *        sinon catégorie vide (« à compléter ») et compte par défaut.
 * RM-5 : une opération identique déjà enregistrée (date, montant, libellé) est signalée.
 */
final class EntrySuggestionService
{
    public const HISTORY_DAYS = 180;

    /**
     * @param  list<ParsedNoteLine>  $lines
     * @return list<array<string, mixed>>
     */
    public function preview(User $user, array $lines, ?Account $defaultAccount): array
    {
        $habits = $this->habits($user);
        $existing = $this->existingKeys($user, $lines);

        return array_map(function (ParsedNoteLine $line) use ($habits, $existing, $defaultAccount) {
            $row = $line->toArray() + ['category_id' => null, 'account_id' => null, 'possible_duplicate' => false];

            if ($line->status === 'unparsed') {
                return $row;
            }

            $habit = $line->label === null ? null : ($habits[$line->type][self::key($line->label)] ?? null);
            $row['category_id'] = $habit['category_id'] ?? null;
            $row['account_id'] = $habit['account_id'] ?? $defaultAccount?->id;
            $row['possible_duplicate'] = isset($existing[$this->entryKey($line->date, $line->amount, $line->label)]);
            $row['status'] = $line->status === 'ok' && $row['category_id'] !== null ? 'ok' : 'incomplete';

            return $row;
        }, $lines);
    }

    /** Libellé normalisé : casse, accents et espaces sans importance. */
    public static function key(string $label): string
    {
        return (string) Str::of($label)->ascii()->lower()->squish();
    }

    /**
     * Habitudes par sens puis par libellé : catégorie et compte les plus fréquents.
     *
     * @return array<string, array<string, array{category_id: ?string, account_id: ?string}>>
     */
    private function habits(User $user): array
    {
        $history = Transaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', ['income', 'expense'])
            ->where('status', '!=', Transaction::STATUS_PENDING)
            ->whereNotNull('beneficiary')
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', Category::RECONCILIATION_IDS)
            ->where('date', '>=', now()->subDays(self::HISTORY_DAYS)->toDateString())
            ->orderByDesc('date')
            ->get(['type', 'beneficiary', 'category_id', 'account_id']);

        $habits = [];
        foreach ($history->groupBy(fn (Transaction $t) => $t->type.'|'.self::key($t->beneficiary)) as $group => $rows) {
            [$type, $key] = explode('|', $group, 2);
            $habits[$type][$key] = [
                'category_id' => $this->mostFrequent($rows->pluck('category_id')),
                'account_id' => $this->mostFrequent($rows->pluck('account_id')),
            ];
        }

        return $habits;
    }

    /**
     * @param  list<ParsedNoteLine>  $lines
     * @return array<string, true>
     */
    private function existingKeys(User $user, array $lines): array
    {
        $dates = array_values(array_unique(array_filter(array_map(fn (ParsedNoteLine $l) => $l->date, $lines))));
        if ($dates === []) {
            return [];
        }

        sort($dates);

        // Intervalle plutôt que whereIn : la colonne peut contenir « Y-m-d 00:00:00 » selon le moteur
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$dates[0], end($dates).' 23:59:59'])
            ->get(['date', 'amount', 'beneficiary'])
            ->mapWithKeys(fn (Transaction $t) => [$this->entryKey($t->date->toDateString(), $t->amount, $t->beneficiary) => true])
            ->all();
    }

    private function entryKey(?string $date, ?int $amount, ?string $label): string
    {
        return $date.'|'.$amount.'|'.self::key((string) $label);
    }

    /** Valeur la plus fréquente ; à égalité, la plus récente (l'historique est trié par date décroissante). */
    private function mostFrequent(Collection $values): ?string
    {
        $counts = $values->filter()->countBy();
        if ($counts->isEmpty()) {
            return null;
        }
        $best = $counts->max();

        return $values->first(fn ($v) => $v !== null && $counts[$v] === $best);
    }
}
