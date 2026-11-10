<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BalanceHistory;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattrapage unique du correctif P4, à lancer une fois juste après le déploiement.
 *
 * Avant le correctif, une transaction `pending_validation` (import SMS) modifiait le solde
 * dès sa création. Désormais elle ne compte qu'à sa validation : sans ce rattrapage, valider
 * une transaction en attente créée AVANT le déploiement la compterait deux fois.
 *
 * Idempotente : chaque transaction rattrapée laisse une ligne `balance_history` marquée,
 * et une seconde exécution la saute. Les transactions créées après le correctif (avec
 * `sms_fingerprint`) sont exclues : la commande reste sûre si elle est relancée plus tard.
 */
final class ReversePendingTransactionsBalance extends Command
{
    public const MARKER = 'Rattrapage P4 : annulation transaction en attente';

    protected $signature = 'transactions:reverse-pending-balance {--dry-run : Afficher sans rien modifier}';

    protected $description = 'Retire du solde les transactions en attente de validation créées avant le correctif P4';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $done = 0;
        $skipped = 0;

        Transaction::where('status', Transaction::STATUS_PENDING)
            // Seules les lignes créées par l'ancien code (sans empreinte SMS) ont touché le solde ;
            // celles du webhook corrigé portent toujours une empreinte et ne sont jamais comptées.
            ->whereNull('sms_fingerprint')
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('created_at')
            ->each(function (Transaction $transaction) use ($dryRun, &$done, &$skipped) {
                if ($this->alreadyReversed($transaction)) {
                    $skipped++;

                    return;
                }

                $delta = $transaction->type === 'income' ? -$transaction->amount : $transaction->amount;
                $this->line(sprintf('%s %s %d FCFA sur le compte %s', $dryRun ? '[dry-run]' : 'Annulé', $transaction->id, $delta, $transaction->account_id));

                if (! $dryRun) {
                    DB::transaction(fn () => Account::withTrashed()->findOrFail($transaction->account_id)->adjustBalance(
                        $delta,
                        $delta > 0 ? 'income' : 'expense',
                        'transaction',
                        $transaction->id,
                        self::MARKER,
                    ));
                }
                $done++;
            });

        $this->info(sprintf('%d transaction(s) %s, %d déjà rattrapée(s).', $done, $dryRun ? 'à rattraper' : 'rattrapée(s)', $skipped));

        return self::SUCCESS;
    }

    private function alreadyReversed(Transaction $transaction): bool
    {
        return BalanceHistory::where('reference_type', 'transaction')
            ->where('reference_id', $transaction->id)
            ->where('description', self::MARKER)
            ->exists();
    }
}
