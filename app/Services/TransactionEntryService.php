<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlannedPurchase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement d'une opération saisie (module dette-qualite) : l'opération et son achat prévu
 * (association ou « acheter quand même », module achats-prevus) dans une même transaction.
 */
final class TransactionEntryService
{
    public function __construct(private readonly PlannedPurchaseService $purchases) {}

    /**
     * @param  array<string, mixed>  $data  champs validés de l'opération
     *
     * @throws \DomainException ALREADY_LINKED si l'achat prévu n'est plus disponible (rien n'est enregistré)
     */
    public function record(User $user, array $data, ?string $plannedPurchaseId, bool $acceptOutOfList): Transaction
    {
        return DB::transaction(function () use ($user, $data, $plannedPurchaseId, $acceptOutOfList) {
            $transaction = $user->transactions()->create($data);

            if ($plannedPurchaseId !== null && $transaction->type === 'expense') {
                $this->purchases->link(PlannedPurchase::findOrFail($plannedPurchaseId), $transaction);
            } elseif ($acceptOutOfList && $this->purchases->isSubjectToRule($transaction)) {
                $this->purchases->acceptUnplanned($transaction);
            }

            return $transaction;
        });
    }
}
