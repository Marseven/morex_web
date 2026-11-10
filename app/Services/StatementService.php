<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre les décisions prises sur un relevé (module releves-sms).
 *
 * RM-2 : les frais deviennent une dépense séparée « Frais bancaires », liée à la ligne.
 * RM-3 : une opération n'est rapprochée qu'une fois (ALREADY_MATCHED sinon).
 * RM-4 : seules les lignes marquées « create » deviennent des opérations.
 * RM-5 : tout ou rien (une transaction DB) ; les soldes bougent par les événements de Transaction.
 */
final class StatementService
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{matched: int, created: int, ignored: int, fees_count: int, fees_total: int}
     */
    public function apply(Account $account, array $lines): array
    {
        $summary = ['matched' => 0, 'created' => 0, 'ignored' => 0, 'fees_count' => 0, 'fees_total' => 0];

        try {
            DB::transaction(function () use ($account, $lines, &$summary) {
                foreach ($lines as $i => $line) {
                    $this->applyLine($account, $line, $i, $summary);
                }
            });
        } catch (QueryException $e) {
            // Référence déjà importée (index unique compte + référence)
            throw ValidationException::withMessages(['lines' => ['ALREADY_MATCHED : une ligne de ce relevé est déjà enregistrée. Relancez l\'analyse.']]);
        }

        return $summary;
    }

    /** @param  array<string, mixed>  $line */
    private function applyLine(Account $account, array $line, int $index, array &$summary): void
    {
        $statement = new StatementLine([
            'account_id' => $account->id,
            'date' => $line['date'],
            'amount' => $line['amount'],
            'fee' => $line['fee'],
            'label' => $line['label'] ?? null,
            'external_ref' => $line['reference'] ?? null,
            'source' => 'paste',
        ]);
        $statement->user_id = $account->user_id;

        if ($line['action'] === 'ignore') {
            $statement->status = StatementLine::STATUS_IGNORED;
            $statement->save();
            $summary['ignored']++;

            return;
        }

        $operation = $line['action'] === 'match'
            ? $this->matchable($account, (string) $line['transaction_id'], (int) $line['amount'], $index)
            : $this->createOperation($account, $line);

        $statement->transaction_id = $operation->id;
        $statement->status = $line['action'] === 'match' ? StatementLine::STATUS_MATCHED : StatementLine::STATUS_CREATED;

        $pointed = [$operation->id];
        if ((int) $line['fee'] > 0) {
            $fee = $this->createFee($account, $line);
            $statement->fee_transaction_id = $fee->id;
            $pointed[] = $fee->id;
            $summary['fees_count']++;
            $summary['fees_total'] += (int) $line['fee'];
        }
        $statement->save();

        // Pointage (module rapprochement) sans déclencher les événements de solde
        Transaction::whereKey($pointed)->update(['reconciled_at' => now()]);
        $summary[$line['action'] === 'match' ? 'matched' : 'created']++;
    }

    private function matchable(Account $account, string $transactionId, int $amount, int $index): Transaction
    {
        $tx = Transaction::whereKey($transactionId)
            ->where('user_id', $account->user_id)
            ->where('account_id', $account->id)
            ->where('type', $amount < 0 ? 'expense' : 'income')
            ->lockForUpdate()
            ->first();

        $alreadyLinked = $tx !== null && StatementLine::where('transaction_id', $tx->id)->exists();
        if ($tx === null || $alreadyLinked) {
            throw ValidationException::withMessages(["lines.{$index}.transaction_id" => [
                $tx === null ? 'Opération introuvable sur ce compte.' : 'ALREADY_MATCHED : opération déjà rapprochée.',
            ], 'lines' => ['Une ligne ne peut pas être enregistrée.']]);
        }

        return $tx;
    }

    /** @param  array<string, mixed>  $line */
    private function createOperation(Account $account, array $line): Transaction
    {
        $tx = new Transaction([
            'amount' => abs((int) $line['amount']),
            'type' => (int) $line['amount'] < 0 ? 'expense' : 'income',
            'category_id' => $line['category_id'],
            'account_id' => $account->id,
            'beneficiary' => $line['label'] ?? null,
            'date' => $line['date'],
            'status' => 'confirmed',
        ]);
        $tx->user_id = $account->user_id;
        $tx->save();

        return $tx;
    }

    /** @param  array<string, mixed>  $line */
    private function createFee(Account $account, array $line): Transaction
    {
        $fee = new Transaction([
            'amount' => (int) $line['fee'],
            'type' => 'expense',
            'category_id' => Category::BANK_FEES_ID,
            'account_id' => $account->id,
            'beneficiary' => 'Frais'.(isset($line['label']) ? ' : '.$line['label'] : ''),
            'date' => $line['date'],
            'status' => 'confirmed',
        ]);
        $fee->user_id = $account->user_id;
        $fee->save();

        return $fee;
    }
}
