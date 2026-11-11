<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        return RecurringTransaction::due()->pluck('id')->sum(fn (string $id) => $this->catchUp($id));
    }

    /** Échéances dues d'un utilisateur (commande ou bouton « Générer »). */
    public function runForUser(User $user): int
    {
        return RecurringTransaction::where('user_id', $user->id)->due()->pluck('id')
            ->sum(fn (string $id) => $this->catchUp($id));
    }

    /** Une échéance, même pas encore due (bouton « Générer » d'une récurrence) : l'opération créée, ou null. */
    public function generateNext(RecurringTransaction $recurring): ?Transaction
    {
        return $this->process($recurring->id, onlyDue: false, limit: 1)[0] ?? null;
    }

    /** Une récurrence en échec est signalée sans arrêter les autres (montants jamais journalisés). */
    private function catchUp(string $id): int
    {
        try {
            return count($this->process($id, onlyDue: true, limit: self::MAX_PER_RUN));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('recurrence.failed', ['recurring_transaction_id' => $id]);

            return 0;
        }
    }

    /** @return list<Transaction> opérations créées */
    private function process(string $id, bool $onlyDue, int $limit): array
    {
        return DB::transaction(function () use ($id, $onlyDue, $limit) {
            $recurring = RecurringTransaction::whereKey($id)->lockForUpdate()->first();
            if ($recurring === null || ! $recurring->is_active) {
                return [];
            }

            // Compte et catégorie doivent exister (et le compte appartenir au même utilisateur), sinon arrêt signalé
            $gone = match (true) {
                ! Account::whereKey($recurring->account_id)->where('user_id', $recurring->user_id)->exists() => 'account_gone',
                $recurring->category_id !== null && ! Category::whereKey($recurring->category_id)->exists() => 'category_gone',
                default => null,
            };
            if ($gone !== null) {
                $recurring->forceFill(['is_active' => false])->save();
                Log::warning("recurrence.{$gone}", ['recurring_transaction_id' => $recurring->id]);

                return [];
            }

            $created = [];
            for ($done = 0; $done < $limit && $recurring->is_active; $done++) {
                if ($onlyDue && $recurring->next_due_date->toDateString() > now()->toDateString()) {
                    break;
                }
                if ($this->stopsHere($recurring)) {
                    $recurring->forceFill(['is_active' => false])->save();
                    break;
                }

                $transaction = $this->createOccurrence($recurring);
                if ($transaction !== null) {
                    $created[] = $transaction;
                }
                $recurring->updateNextDueDate(consume: $transaction !== null);
            }

            return $created;
        });
    }

    private function stopsHere(RecurringTransaction $recurring): bool
    {
        return ($recurring->remaining_occurrences !== null && $recurring->remaining_occurrences <= 0)
            || ($recurring->end_date !== null && $recurring->next_due_date->gt($recurring->end_date));
    }

    /** RM-1 : null si l'échéance existe déjà (rejeu, synchro qui remet une ancienne date). */
    private function createOccurrence(RecurringTransaction $recurring): ?Transaction
    {
        $exists = Transaction::withTrashed()
            ->where('recurring_transaction_id', $recurring->id)
            ->whereDate('occurrence_date', $recurring->next_due_date->toDateString())
            ->exists();
        if ($exists) {
            return null;
        }

        try {
            return DB::transaction(fn () => $recurring->generateTransaction());
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
