<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\StatementLine;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function apply(Account $account, array $lines, string $source = 'paste'): array
    {

        $summary = ['matched' => 0, 'created' => 0, 'ignored' => 0, 'fees_count' => 0, 'fees_total' => 0];

        try {
            DB::transaction(function () use ($account, $lines, &$summary, $source) {
                foreach ($lines as $i => $line) {
                    $this->applyLine($account, $line, $i, $summary, $source);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Seuls les doublons (référence, opération ou frais déjà liés) sont traduits ; le reste remonte
            throw $this->alreadyMatched('lines', 'une ligne de ce relevé est déjà enregistrée. Relancez l\'analyse.');
        }

        return $summary;
    }

    /** @param  array<string, mixed>  $line */
    private function applyLine(Account $account, array $line, int $index, array &$summary, string $source): void
    {
        $statement = new StatementLine([
            'account_id' => $account->id,
            'date' => $line['date'],
            'amount' => $line['amount'],
            'fee' => $line['fee'],
            'label' => $line['label'] ?? null,
            'external_ref' => $line['reference'] ?? null,
            'source' => $source, // paste, csv ou sms (module sms-iphone)
        ]);
        $statement->user_id = $account->user_id;

        // Rejeu sans référence : même compte, date, montant et libellé déjà enregistrés
        if (($line['reference'] ?? null) === null && $this->sameLineExists($account, $line)) {
            throw $this->alreadyMatched("lines.{$index}", 'cette ligne de relevé est déjà enregistrée.');
        }

        if ($line['action'] === 'ignore') {
            $statement->status = StatementLine::STATUS_IGNORED;
            $statement->save();
            $summary['ignored']++;

            return;
        }

        // RM-1 revérifié côté serveur : les frais ne sont jamais repris tels quels du client
        if ($line['action'] === 'match') {
            $operation = $this->matchable($account, (string) $line['transaction_id'], (int) $line['amount'], $index);
            $line['fee'] = $this->verifiedFee($operation, $line, $index);
        } else {
            $this->assertFeeWithinCeiling((int) $line['fee'], abs((int) $line['amount']), $index);
            $operation = $this->createOperation($account, $line, $index);
        }
        $statement->fee = (int) $line['fee'];

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
            ->where('status', '!=', Transaction::STATUS_PENDING)
            ->lockForUpdate()
            ->first();

        $alreadyLinked = $tx !== null && StatementLine::where(fn ($q) => $q->where('transaction_id', $tx->id)->orWhere('fee_transaction_id', $tx->id))->exists();
        if ($tx === null || $alreadyLinked) {
            throw ValidationException::withMessages(["lines.{$index}.transaction_id" => [
                $tx === null ? 'Opération introuvable sur ce compte.' : 'ALREADY_MATCHED : opération déjà rapprochée.',
            ], 'lines' => ['Une ligne ne peut pas être enregistrée.']]);
        }

        return $tx;
    }

    /**
     * Frais retenus pour un rapprochement (RM-1) : la paire relevé ↔ opération doit tenir.
     *
     * @param  array<string, mixed>  $line
     */
    private function verifiedFee(Transaction $tx, array $line, int $index): int
    {
        $days = abs($tx->date->diffInDays(CarbonImmutable::parse($line['date'])));
        if ($days > StatementMatcher::DAY_TOLERANCE) {
            throw $this->invalid($index, 'date trop éloignée de celle de l\'opération.');
        }

        $statement = abs((int) $line['amount']);
        $declared = (int) $line['fee'];

        $fee = match (true) {
            $tx->amount === $statement => $declared,                         // frais annoncés à part (SMS)
            $declared > 0 && $tx->amount === $statement + $declared => 0,    // frais déjà inclus dans la note
            $statement > $tx->amount => $statement - $tx->amount,            // relevé = opération + frais
            default => null,
        };
        if ($fee === null) {
            throw $this->invalid($index, 'montant sans rapport avec l\'opération choisie.');
        }
        $this->assertFeeWithinCeiling($fee, $tx->amount, $index);

        return $fee;
    }

    private function assertFeeWithinCeiling(int $fee, int $base, int $index): void
    {
        if ($fee > StatementMatcher::feeCeiling($base)) {
            throw $this->invalid($index, 'frais supérieurs au plafond ('.StatementMatcher::feeCeiling($base).' FCFA).');
        }
    }

    /** @param  array<string, mixed>  $line */
    private function sameLineExists(Account $account, array $line): bool
    {
        return StatementLine::where('account_id', $account->id)
            ->whereBetween('date', [$line['date'], $line['date'].' 23:59:59'])
            ->where('amount', $line['amount'])
            ->get(['label'])
            ->contains(fn (StatementLine $l) => Str::lower((string) $l->label) === Str::lower((string) ($line['label'] ?? '')));
    }

    private function invalid(int $index, string $message): ValidationException
    {
        return ValidationException::withMessages(["lines.{$index}" => ['Ligne '.($index + 1).' : '.$message], 'lines' => ['Une ligne ne peut pas être enregistrée.']]);
    }

    private function alreadyMatched(string $key, string $message): ValidationException
    {
        return ValidationException::withMessages([$key => ['ALREADY_MATCHED : '.$message], 'lines' => ['ALREADY_MATCHED : '.$message]]);
    }

    /** @param  array<string, mixed>  $line */
    private function createOperation(Account $account, array $line, int $index): Transaction
    {
        $type = (int) $line['amount'] < 0 ? 'expense' : 'income';
        if (! Category::whereKey($line['category_id'])->where('type', $type)->exists()) {
            throw $this->invalid($index, 'catégorie incompatible avec le sens de l\'opération.');
        }

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
