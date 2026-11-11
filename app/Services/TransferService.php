<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transferts avec frais (module transferts-frais 3c).
 *
 * RM-1 les frais sont une dépense séparée « Frais bancaires », sur le compte source, à la date du transfert.
 * RM-2 le transfert porte le lien ; les soldes ne bougent que par les événements de Transfer et Transaction.
 * RM-3 0 ≤ frais ≤ montant ; à 0, pas de dépense.
 * RM-4 frais proposés : ceux du dernier transfert du même couple de comptes.
 */
final class TransferService
{
    /** @param array{from_account_id: string, to_account_id: string, amount: int, date: string, description?: ?string, fee?: ?int} $data */
    public function create(User $user, array $data): Transfer
    {
        return DB::transaction(function () use ($user, $data) {
            $transfer = new Transfer(collect($data)->except('fee')->all());
            $transfer->user_id = $user->id;
            $transfer->save();
            $this->applyFee($transfer, (int) ($data['fee'] ?? 0));

            return $transfer->fresh(['feeTransaction']);
        });
    }

    /** @param array<string, mixed> $data champs à modifier ; « fee » absent : frais inchangés (montant, compte ou date suivis) */
    public function update(Transfer $transfer, array $data): Transfer
    {
        return DB::transaction(function () use ($transfer, $data) {
            $transfer->update(collect($data)->except('fee')->all());
            $this->applyFee($transfer, array_key_exists('fee', $data) ? (int) $data['fee'] : (int) $transfer->fee);

            return $transfer->fresh(['feeTransaction']);
        });
    }

    public function delete(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer->feeTransaction?->delete();
            $transfer->delete();
        });
    }

    public function suggestedFee(User $user, string $fromAccountId, string $toAccountId): int
    {
        return (int) Transfer::where('user_id', $user->id)
            ->where('from_account_id', $fromAccountId)
            ->where('to_account_id', $toAccountId)
            ->latest('date')->latest('created_at')
            ->value('fee');
    }

    /**
     * RM-4 pour l'écran : derniers frais par couple « source:destination » (les 200 derniers transferts suffisent).
     *
     * @return array<string, int>
     */
    public function lastFeesByPair(User $user): array
    {
        return Transfer::where('user_id', $user->id)
            ->latest('date')->latest('created_at')->limit(200)
            ->get(['from_account_id', 'to_account_id', 'fee'])
            ->unique(fn (Transfer $t) => $t->from_account_id.':'.$t->to_account_id)
            ->mapWithKeys(fn (Transfer $t) => [$t->from_account_id.':'.$t->to_account_id => (int) $t->fee])
            ->all();
    }

    /** Synchro (événements désactivés) : les frais d'un transfert supprimé sont supprimés après coup, avec leurs effets. */
    public function releaseOrphanFees(User $user): void
    {
        Transfer::onlyTrashed()->where('user_id', $user->id)->whereNotNull('fee_transaction_id')->get()
            ->each(function (Transfer $transfer) {
                Transaction::find($transfer->fee_transaction_id)?->delete();
                $transfer->forceFill(['fee_transaction_id' => null])->saveQuietly();
            });
    }

    private function applyFee(Transfer $transfer, int $fee): void
    {
        $current = $transfer->fee_transaction_id ? Transaction::find($transfer->fee_transaction_id) : null;

        if ($fee <= 0) {
            $current?->delete();
            $transfer->forceFill(['fee' => 0, 'fee_transaction_id' => null])->saveQuietly();

            return;
        }

        $values = [
            'amount' => $fee,
            'type' => 'expense',
            'category_id' => Category::BANK_FEES_ID,
            'account_id' => $transfer->from_account_id,
            'date' => $transfer->date,
            'beneficiary' => 'Frais de transfert',
            'description' => $this->label($transfer),
        ];

        if ($current !== null) {
            $current->update($values);
        } else {
            $current = new Transaction($values);
            $current->user_id = $transfer->user_id;
            $current->save();
        }

        $transfer->forceFill(['fee' => $fee, 'fee_transaction_id' => $current->id])->saveQuietly();
    }

    private function label(Transfer $transfer): string
    {
        $names = Account::withTrashed()->whereIn('id', [$transfer->from_account_id, $transfer->to_account_id])->pluck('name', 'id');

        return sprintf('Frais de transfert %s → %s', $names[$transfer->from_account_id] ?? '?', $names[$transfer->to_account_id] ?? '?');
    }
}
