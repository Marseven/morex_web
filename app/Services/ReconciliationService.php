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

    /** RM-2 : opérations du compte à pointer, de la plus ancienne à la plus récente. */
    public function uncleared(Account $account): Collection
    {
        return $this->unclearedQuery($account)->orderBy('date')->orderBy('created_at')->get();
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
            $cleared = $this->checkedTransactions($account, $clearedIds);

            $pendingOnStatement = $this->unclearedQuery($account)
                ->whereNotIn('id', $cleared->modelKeys())
                ->get()
                ->sum(fn (Transaction $t) => $t->type === 'income' ? $t->amount : -$t->amount);

            $difference = $statementBalance - ($before - $pendingOnStatement);

            return $this->record($account, $statementBalance, $before, $difference, $cleared, $note);
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
     * Chaque opération cochée doit être une opération à pointer de CE compte (protection IDOR).
     *
     * @param  list<string>  $ids
     */
    private function checkedTransactions(Account $account, array $ids): Collection
    {
        $ids = array_values(array_unique($ids));
        $found = $ids === [] ? new Collection : $this->unclearedQuery($account)->whereKey($ids)->get();

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
            ->whereNull('reconciled_at')
            ->whereDate('date', '<=', now()->toDateString());
    }
}
