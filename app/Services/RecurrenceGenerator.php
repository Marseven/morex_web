<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Génération des opérations récurrentes par le serveur (module recurrences-serveur 6).
 *
 * RM-1 une opération par (récurrence, échéance), garantie par l'index unique.
 * RM-2 une récurrence est traitée sous verrou, dans une transaction.
 * RM-3 rattrapage limité à MAX_PER_RUN échéances par passage.
 */
final class RecurrenceGenerator
{
    public const MAX_PER_RUN = 60;

    /** Toutes les récurrences échues, tous utilisateurs (commande planifiée). */
    public function runAll(): int
    {
        return RecurringTransaction::due()->distinct()->pluck('user_id')
            ->sum(fn ($userId) => ($user = User::find($userId)) ? $this->runForUser($user) : 0);
    }

    /** Échéances dues d'un utilisateur (commande ou bouton « Générer »). */
    public function runForUser(User $user): int
    {
        return RecurringTransaction::where('user_id', $user->id)->due()->pluck('id')
            ->sum(fn (string $id) => $this->catchUp($id));
    }

    /** Une échéance, même pas encore due (bouton « Générer » d'une récurrence). */
    public function generateNext(RecurringTransaction $recurring): int
    {
        return $this->process($recurring->id, onlyDue: false, limit: 1);
    }

    private function catchUp(string $id): int
    {
        return $this->process($id, onlyDue: true, limit: self::MAX_PER_RUN);
    }

    private function process(string $id, bool $onlyDue, int $limit): int
    {
        return DB::transaction(function () use ($id, $onlyDue, $limit) {
            $recurring = RecurringTransaction::whereKey($id)->lockForUpdate()->first();
            if ($recurring === null || ! $recurring->is_active) {
                return 0;
            }

            // Le compte doit exister et appartenir au même utilisateur, sinon la récurrence est arrêtée
            if (! Account::whereKey($recurring->account_id)->where('user_id', $recurring->user_id)->exists()) {
                $recurring->forceFill(['is_active' => false])->save();

                return 0;
            }

            $created = 0;
            for ($done = 0; $done < $limit && $recurring->is_active; $done++) {
                if ($onlyDue && $recurring->next_due_date->toDateString() > now()->toDateString()) {
                    break;
                }
                if ($this->stopsHere($recurring)) {
                    $recurring->forceFill(['is_active' => false])->save();
                    break;
                }

                $isNew = $this->createOccurrence($recurring);
                $created += $isNew ? 1 : 0;
                $recurring->updateNextDueDate(consume: $isNew);
            }

            return $created;
        });
    }

    private function stopsHere(RecurringTransaction $recurring): bool
    {
        return ($recurring->remaining_occurrences !== null && $recurring->remaining_occurrences <= 0)
            || ($recurring->end_date !== null && $recurring->next_due_date->gt($recurring->end_date));
    }

    /** RM-1 : faux si l'échéance existe déjà (rejeu, synchro qui remet une ancienne date). */
    private function createOccurrence(RecurringTransaction $recurring): bool
    {
        $exists = Transaction::withTrashed()
            ->where('recurring_transaction_id', $recurring->id)
            ->whereDate('occurrence_date', $recurring->next_due_date->toDateString())
            ->exists();
        if ($exists) {
            return false;
        }

        try {
            DB::transaction(fn () => $recurring->generateTransaction());
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
