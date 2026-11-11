<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Reconciliation;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rapprochement d'un compte avec son relevé (module rapprochement).
 *
 * RM-1 : un écart ne modifie le solde qu'au travers d'une transaction (événements de Transaction
 * → Account::adjustBalance), jamais en écrivant `balance`.
 * RM-5 : écart = relevé − (solde Morex − somme signée des opérations non cochées).
 */
final class ReconciliationService
{
    public const GAP_LABEL = 'Écart de rapprochement';

    /**
     * Fenêtre de pointage : seules les N opérations non pointées les plus récentes sont proposées.
     * Les plus anciennes sont considérées comme passées sur le relevé (RM-3 : base de départ),
     * ce qui garde l'écart affiché identique à l'écart enregistré.
     */
    public static int $windowSize = 500;

    /** RM-2 : opérations du compte à pointer (fenêtre), de la plus ancienne à la plus récente. */
    public function uncleared(Account $account): Collection
    {
        return $this->windowQuery($account)->get()->sortBy([['date', 'asc'], ['created_at', 'asc']])->values();
    }

    /** Opérations non pointées hors fenêtre, qui seront considérées comme pointées. */
    public function olderThanWindowCount(Account $account): int
    {
        return max(0, $this->unclearedQuery($account)->count() - self::$windowSize);
    }

    public function lastReconciliation(Account $account): ?Reconciliation
    {
        return Reconciliation::where('account_id', $account->id)->latest('reconciled_at')->first();
    }

    /**
     * @param  list<string>  $clearedIds  opérations présentes sur le relevé
     */
    public function reconcile(Account $account, int $statementBalance, array $clearedIds, ?string $note = null): Reconciliation
    {
        return DB::transaction(function () use ($account, $statementBalance, $clearedIds, $note) {
            $before = $this->lockedBalance($account);
            $window = $this->windowQuery($account)->get();
            $cleared = $this->checkedTransactions($window, $clearedIds);

            // RM-5 : ne restent « pas encore sur le relevé » que les opérations de la fenêtre non cochées
            $pendingOnStatement = $window
                ->reject(fn (Transaction $t) => $cleared->contains($t))
                ->sum(fn (Transaction $t) => $t->type === 'income' ? $t->amount : -$t->amount);

            $difference = $statementBalance - ($before - $pendingOnStatement);

            // Hors fenêtre : considérées comme passées (base de départ)
            $older = $this->unclearedQuery($account)->whereNotIn('id', $window->modelKeys())->get();

            return $this->record($account, $statementBalance, $before, $difference, $cleared->merge($older), $note);
        });
    }

    /** Ancien « Ajuster le solde » : le solde voulu est atteint par une opération d'écart tracée. */
    public function adjustTo(Account $account, int $desiredBalance): Reconciliation
    {
        return DB::transaction(function () use ($account, $desiredBalance) {
            $before = $this->lockedBalance($account);

            return $this->record($account, $desiredBalance, $before, $desiredBalance - $before, new Collection, 'Ajustement manuel du solde');
        });
    }

    private function record(Account $account, int $statement, int $before, int $difference, Collection $cleared, ?string $note): Reconciliation
    {
        $now = now();
        $gap = $difference === 0 ? null : $this->createGapTransaction($account, $difference);

        $ids = $cleared->modelKeys();
        if ($gap) {
            $ids[] = $gap->id;
        }
        if ($ids !== []) {
            Transaction::whereKey($ids)->update(['reconciled_at' => $now]);
        }

        $reconciliation = new Reconciliation([
            'account_id' => $account->id,
            'statement_balance' => $statement,
            'balance_before' => $before,
            'difference' => $difference,
            'adjustment_transaction_id' => $gap?->id,
            'reconciled_at' => $now,
            'note' => $note,
        ]);
        $reconciliation->user_id = $account->user_id;
        $reconciliation->save();

        $account->refresh();

        return $reconciliation;
    }

    private function createGapTransaction(Account $account, int $difference): Transaction
    {
        $gap = new Transaction([
            'amount' => abs($difference),
            'type' => $difference > 0 ? 'income' : 'expense',
            'category_id' => $difference > 0 ? Category::RECONCILIATION_INCOME_ID : Category::RECONCILIATION_EXPENSE_ID,
            'account_id' => $account->id,
            'description' => self::GAP_LABEL,
            'date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        $gap->user_id = $account->user_id;
        $gap->save(); // l'événement « created » ajuste le solde sous verrou

        return $gap;
    }

    /** Solde relu sous verrou : deux validations simultanées ne partent pas du même solde. */
    private function lockedBalance(Account $account): int
    {
        return (int) Account::withTrashed()->whereKey($account->id)->lockForUpdate()->value('balance');
    }

    /**
     * Chaque opération cochée doit être une opération proposée pour CE compte (protection IDOR).
     *
     * @param  list<string>  $ids
     */
    private function checkedTransactions(Collection $window, array $ids): Collection
    {
        $ids = array_values(array_unique($ids));
        $found = $window->whereIn('id', $ids)->values();

        if ($found->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'cleared_ids' => ['INVALID_RECONCILIATION : une opération ne correspond plus à ce compte. Rechargez la page.'],
            ]);
        }

        return $found;
    }

    private function unclearedQuery(Account $account): Builder
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->where('user_id', $account->user_id)
            ->whereIn('type', ['income', 'expense'])
            ->where('status', '!=', Transaction::STATUS_PENDING)
            // Pas de borne de date : une opération future confirmée compte déjà dans le solde
            ->whereNull('reconciled_at');
    }

    private function windowQuery(Account $account): Builder
    {
        return $this->unclearedQuery($account)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(self::$windowSize);
    }
}
