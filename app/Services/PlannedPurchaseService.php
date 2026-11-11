<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\PlannedPurchase;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Achats prévus et règle « tout achat de 30 000 ou plus hors liste attend le cycle suivant » (module achats-prevus 5d).
 *
 * RM-1 seuil sur les dépenses comptées de nature courante (ni transit ni pro).
 * RM-2 période = cycle actif, sinon mois civil (comme les plafonds).
 * RM-3 hors liste = dépense au-dessus du seuil liée à aucun achat prévu.
 * RM-4 un achat ne se lie qu'à une opération, et inversement.
 */
final class PlannedPurchaseService
{
    public const THRESHOLD = 30000;

    public function __construct(private readonly BudgetAlertService $periods) {}

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: bool} début, fin incluse, « est un cycle » */
    public function period(User $user): array
    {
        return $this->periods->period($user);
    }

    /** RM-2 : lendemain de la fin du cycle, sinon le 1er du mois suivant. */
    public function nextPeriodStart(User $user): CarbonImmutable
    {
        [, $to] = $this->period($user);

        return $to !== null ? $to->addDay()->startOfDay() : CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
    }

    /** @param array{name: string, estimated_amount: int, category_id?: ?string, planned_for?: ?string} $data */
    public function plan(User $user, array $data): PlannedPurchase
    {
        $purchase = new PlannedPurchase([
            'name' => $data['name'],
            'estimated_amount' => $data['estimated_amount'],
            'category_id' => $data['category_id'] ?? null,
            'planned_for' => $data['planned_for'] ?? $this->period($user)[0]->toDateString(),
        ]);
        $purchase->forceFill(['user_id' => $user->id, 'status' => PlannedPurchase::STATUS_PLANNED])->save();

        return $purchase;
    }

    /** « Reporter au cycle suivant » : rien n'est dépensé, l'achat rejoint la liste suivante. */
    public function postpone(User $user, array $data): PlannedPurchase
    {
        return $this->plan($user, ['planned_for' => $this->nextPeriodStart($user)->toDateString()] + $data);
    }

    /**
     * RM-4 : lie l'achat à l'opération réalisée.
     * Mise à jour gardée (status planned, sans lien) : deux envois simultanés ne peuvent pas lier le même achat.
     */
    public function link(PlannedPurchase $purchase, Transaction $transaction): void
    {
        if ($purchase->user_id !== $transaction->user_id) {
            throw new \DomainException('FOREIGN_TRANSACTION');
        }

        $values = ['status' => PlannedPurchase::STATUS_BOUGHT, 'transaction_id' => $transaction->id, 'bought_at' => now()];

        try {
            $updated = PlannedPurchase::whereKey($purchase->id)
                ->where('user_id', $transaction->user_id)
                ->where('status', PlannedPurchase::STATUS_PLANNED)
                ->whereNull('transaction_id')
                ->update($values + ['updated_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('ALREADY_LINKED'); // l'opération est déjà liée à un autre achat
        }

        if ($updated !== 1) {
            throw new \DomainException('ALREADY_LINKED');
        }

        $purchase->forceFill($values)->syncOriginal();
    }

    /** RM-1 : la règle ne vise que les dépenses au-dessus du seuil, de nature courante (ni transit ni pro). */
    public function isSubjectToRule(Transaction $transaction): bool
    {
        if ($transaction->type !== 'expense' || $transaction->amount < self::THRESHOLD) {
            return false;
        }

        return $transaction->category_id === null
            || Category::whereKey($transaction->category_id)->where('nature', Category::NATURE_REGULAR)->exists();
    }

    /** Une opération supprimée ou qui n'est plus une dépense libère son achat : prévu rouvert, hors liste annulé. */
    public function releaseFor(Transaction $transaction): void
    {
        PlannedPurchase::where('transaction_id', $transaction->id)->get()->each(fn (PlannedPurchase $p) => $p->forceFill([
            'status' => $p->unplanned ? PlannedPurchase::STATUS_CANCELLED : PlannedPurchase::STATUS_PLANNED,
            'transaction_id' => null,
            'bought_at' => null,
        ])->save());
    }

    /** Rattrapage des chemins sans événements (synchro) : liens vers une opération supprimée ou devenue autre chose. */
    public function releaseOrphans(User $user): void
    {
        Transaction::withTrashed()
            ->where('user_id', $user->id)
            ->whereIn('id', PlannedPurchase::where('user_id', $user->id)->whereNotNull('transaction_id')->select('transaction_id'))
            ->where(fn ($q) => $q->whereNotNull('deleted_at')->orWhere('type', '!=', 'expense'))
            ->get()
            ->each(fn (Transaction $t) => $this->releaseFor($t));
    }

    /** RM-3 « Acheter quand même » : trace d'un achat hors liste assumé. */
    public function acceptUnplanned(Transaction $transaction): PlannedPurchase
    {
        $purchase = new PlannedPurchase([
            'name' => $transaction->beneficiary ?: ($transaction->description ?: 'Achat hors liste'),
            'estimated_amount' => $transaction->amount,
            'category_id' => $transaction->category_id,
            'planned_for' => $transaction->date->toDateString(),
        ]);
        $purchase->forceFill([
            'user_id' => $transaction->user_id, 'unplanned' => true, 'status' => PlannedPurchase::STATUS_BOUGHT,
            'transaction_id' => $transaction->id, 'bought_at' => now(),
        ]);

        try {
            $purchase->save();
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('ALREADY_LINKED');
        }

        return $purchase;
    }

    /** Achats encore ouverts et déjà permis, toutes catégories (la saisie filtre selon la catégorie choisie). */
    public function openPurchases(User $user): Collection
    {
        [, $to] = $this->period($user);

        return PlannedPurchase::where('user_id', $user->id)
            ->where('status', PlannedPurchase::STATUS_PLANNED)
            ->where('planned_for', '<=', ($to ?? CarbonImmutable::now())->toDateString())
            ->orderBy('planned_for')
            ->limit(50)
            ->get();
    }

    /** RM-1 / RM-3 : dépenses de la période au-dessus du seuil, de nature courante, liées à aucun achat. */
    public function outOfList(User $user, int $limit = 50): Collection
    {
        return $this->outOfListQuery($user)->orderByDesc('date')->limit($limit)->get();
    }

    private function outOfListQuery(User $user): Builder
    {
        [$from, $to] = $this->period($user);

        return Transaction::where('user_id', $user->id)
            ->counted()
            ->where('type', 'expense')
            ->where('amount', '>=', self::THRESHOLD)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereNotIn('category_id', Category::withTrashed()->select('id')->where('nature', '!=', Category::NATURE_REGULAR)))
            ->whereNotIn('id', PlannedPurchase::withTrashed()->whereNotNull('transaction_id')->select('transaction_id'))
            ->where('date', '>=', $from->toDateString())
            ->when($to, fn ($q) => $q->where('date', '<=', $to->toDateString().' 23:59:59'));
    }

    /** Résumé du cycle pour la carte et la page. */
    public function summary(User $user): array
    {
        [$from, $to] = $this->period($user);
        $inPeriod = PlannedPurchase::with('transaction:id,amount')
            ->where('user_id', $user->id)
            ->where('unplanned', false)
            ->where('status', '!=', PlannedPurchase::STATUS_CANCELLED)
            ->where('planned_for', '>=', $from->toDateString())
            ->when($to, fn ($q) => $q->where('planned_for', '<=', $to->toDateString()))
            ->get();
        $bought = $inPeriod->where('status', PlannedPurchase::STATUS_BOUGHT);
        $outOfList = $this->outOfListQuery($user);

        return [
            'planned_count' => $inPeriod->count(),
            'bought_count' => $bought->count(),
            'planned_total' => (int) $inPeriod->sum('estimated_amount'),
            'spent_total' => (int) $bought->sum(fn (PlannedPurchase $p) => $p->transaction?->amount ?? 0),
            'out_of_list_count' => (clone $outOfList)->count(),
            'out_of_list_total' => (int) (clone $outOfList)->sum('amount'),
            'unplanned_count' => PlannedPurchase::where('user_id', $user->id)->where('unplanned', true)
                ->where('status', '!=', PlannedPurchase::STATUS_CANCELLED)
                ->where('planned_for', '>=', $from->toDateString())
                ->when($to, fn ($q) => $q->where('planned_for', '<=', $to->toDateString()))->count(),
            'late' => PlannedPurchase::where('user_id', $user->id)
                ->where('status', PlannedPurchase::STATUS_PLANNED)
                ->where('planned_for', '<', $from->toDateString())
                ->orderBy('planned_for')->limit(20)->get(),
        ];
    }
}
