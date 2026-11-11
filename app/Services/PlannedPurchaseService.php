<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\PlannedPurchase;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    /** RM-4 : lie l'achat à l'opération réalisée. */
    public function link(PlannedPurchase $purchase, Transaction $transaction): void
    {
        if ($purchase->user_id !== $transaction->user_id) {
            throw new \DomainException('FOREIGN_TRANSACTION');
        }
        if ($purchase->transaction_id !== null || PlannedPurchase::where('transaction_id', $transaction->id)->exists()) {
            throw new \DomainException('ALREADY_LINKED');
        }

        $purchase->forceFill([
            'status' => PlannedPurchase::STATUS_BOUGHT,
            'transaction_id' => $transaction->id,
            'bought_at' => now(),
        ]);

        try {
            $purchase->save();
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('ALREADY_LINKED');
        }
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
        $purchase->forceFill(['user_id' => $transaction->user_id, 'unplanned' => true, 'status' => PlannedPurchase::STATUS_PLANNED]);
        $this->link($purchase, $transaction);

        return $purchase;
    }

    /** Achats prévus encore ouverts et déjà permis (période en cours ou antérieure), même catégorie ou sans catégorie. */
    public function candidates(User $user, ?string $categoryId): Collection
    {
        return $this->openPurchases($user)
            ->filter(fn (PlannedPurchase $p) => $p->category_id === null || $p->category_id === $categoryId)
            ->values();
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
    public function outOfList(User $user): Collection
    {
        [$from, $to] = $this->period($user);

        return Transaction::where('user_id', $user->id)
            ->counted()
            ->where('type', 'expense')
            ->where('amount', '>=', self::THRESHOLD)
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereNotIn('category_id', Category::withTrashed()->select('id')->where('nature', '!=', Category::NATURE_REGULAR)))
            ->whereNotIn('id', PlannedPurchase::withTrashed()->whereNotNull('transaction_id')->select('transaction_id'))
            ->where('date', '>=', $from->toDateString())
            ->when($to, fn ($q) => $q->where('date', '<=', $to->toDateString().' 23:59:59'))
            ->orderByDesc('date')
            ->get();
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
        $outOfList = $this->outOfList($user);

        return [
            'planned_count' => $inPeriod->count(),
            'bought_count' => $bought->count(),
            'planned_total' => (int) $inPeriod->sum('estimated_amount'),
            'spent_total' => (int) $bought->sum(fn (PlannedPurchase $p) => $p->transaction?->amount ?? 0),
            'out_of_list_count' => $outOfList->count(),
            'out_of_list_total' => (int) $outOfList->sum('amount'),
            'unplanned_count' => PlannedPurchase::where('user_id', $user->id)->where('unplanned', true)
                ->where('planned_for', '>=', $from->toDateString())->count(),
            'late' => PlannedPurchase::where('user_id', $user->id)
                ->where('status', PlannedPurchase::STATUS_PLANNED)
                ->where('planned_for', '<', $from->toDateString())
                ->orderBy('planned_for')->get(),
        ];
    }
}
