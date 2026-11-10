<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PushNotifier;
use App\Models\BudgetAlert;
use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Plafonds par catégorie et alertes (module plafonds-alertes).
 *
 * RM-1 dépensé = dépenses de la catégorie sur le cycle actif (sinon le mois civil), hors opérations
 *      en attente et écarts de rapprochement.
 * RM-2 seuils 80 % et 100 % ; un saut direct au-delà de 100 % n'envoie que l'alerte 100 %.
 * RM-3 une alerte par (catégorie, période, seuil), garantie par l'index unique.
 */
final class BudgetAlertService
{
    public const THRESHOLDS = [100, 80];

    /** @var array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: bool}> période par utilisateur et par jour */
    private array $periods = [];

    public function __construct(private readonly PushNotifier $push) {}

    /** Un cycle a changé : la période doit être relue. */
    public function forgetPeriods(): void
    {
        $this->periods = [];
    }

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: bool} début, fin incluse, « est un cycle » */
    public function period(User $user): array
    {
        return $this->periods[$user->id.'|'.now()->toDateString()] ??= $this->loadPeriod($user);
    }

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: bool} */
    private function loadPeriod(User $user): array
    {
        $cycle = BudgetCycle::where('user_id', $user->id)->where('status', 'active')->latest('start_date')->first();
        if ($cycle !== null) {
            return [CarbonImmutable::parse($cycle->start_date)->startOfDay(), $cycle->end_date ? CarbonImmutable::parse($cycle->end_date) : null, true];
        }

        return [CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()->startOfDay(), false];
    }

    public function spent(User $user, string $categoryId, CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        $query = Transaction::where('user_id', $user->id)
            ->where('category_id', $categoryId)
            ->where('type', 'expense')
            ->where('status', '!=', Transaction::STATUS_PENDING)
            ->where('date', '>=', $from->toDateString());

        if ($to !== null) {
            $query->where('date', '<=', $to->toDateString().' 23:59:59');
        }

        return (int) $query->sum('amount');
    }

    /** À appeler après l'enregistrement d'une dépense confirmée. */
    public function checkCategory(User $user, string $categoryId): ?BudgetAlert
    {
        $category = Category::find($categoryId);
        // Transit (tontines, avances) : ni plafond ni alerte (module revenus-transit, RM-3)
        if ($category === null || (int) $category->budget_limit <= 0 || $category->nature === Category::NATURE_TRANSIT
            || in_array($category->id, Category::RECONCILIATION_IDS, true)) {
            return null;
        }

        [$from, $to] = $this->period($user);
        $limit = (int) $category->budget_limit;
        $spent = $this->spent($user, $category->id, $from, $to);

        foreach (self::THRESHOLDS as $threshold) {
            if ($spent * 100 < $limit * $threshold) {
                continue;
            }

            // RM-2 : seul le seuil le plus haut franchi est envisagé ; « 80 % » n'a plus de sens après « dépassé »
            if ($threshold < 100 && $this->alreadyAlerted($user, $category, $from, 100)) {
                return null;
            }

            return $this->record($user, $category, $from, $to, $threshold, $spent, $limit);
        }

        return null;
    }

    /** Synchro (événements désactivés) : vérifier toutes les catégories plafonnées de l'utilisateur. */
    public function checkUser(User $user): void
    {
        Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('is_system', true))
            ->where('budget_limit', '>', 0)
            ->where('nature', '!=', Category::NATURE_TRANSIT)
            ->pluck('id')
            ->each(fn (string $id) => $this->checkCategory($user, $id));
    }

    private function record(User $user, Category $category, CarbonImmutable $from, ?CarbonImmutable $to, int $threshold, int $spent, int $limit): ?BudgetAlert
    {
        $alert = new BudgetAlert;
        $alert->forceFill([
            'user_id' => $user->id, 'category_id' => $category->id, 'cycle_start' => $from->toDateString(),
            'threshold' => $threshold, 'spent' => $spent, 'limit' => $limit, 'created_at' => now(),
        ]);

        try {
            $alert->save();
        } catch (UniqueConstraintViolationException) {
            return null; // RM-3 : déjà alerté pour ce seuil et cette période
        }

        // L'alerte est enregistrée : un échec d'envoi ne doit jamais faire échouer la dépense
        try {
            $this->push->sendToUser($user, $this->message($category, $threshold, $spent, $limit, $to));
        } catch (\Throwable $e) {
            report($e);
        }

        return $alert;
    }

    private function alreadyAlerted(User $user, Category $category, CarbonImmutable $from, int $threshold): bool
    {
        return BudgetAlert::where('user_id', $user->id)->where('category_id', $category->id)
            ->whereDate('cycle_start', $from->toDateString())->where('threshold', $threshold)->exists();
    }

    /** @return array{title: string, body: string, url: string, tag: string} */
    private function message(Category $category, int $threshold, int $spent, int $limit, ?CarbonImmutable $to): array
    {
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ');
        $amounts = $fmt($spent).' / '.$fmt($limit).' FCFA';

        if ($threshold === 100) {
            return [
                'title' => 'Plafond dépassé : '.$category->name,
                'body' => $amounts.', soit +'.$fmt($spent - $limit).' au-delà.',
                'url' => '/transactions?category_id='.$category->id,
                'tag' => 'budget-'.$category->id.'-100',
            ];
        }

        $until = $to !== null ? "jusqu'au ".$to->format('d/m') : "jusqu'au prochain salaire";

        return [
            'title' => $category->name.' à '.(int) floor($spent * 100 / $limit).' %',
            'body' => $amounts.'. Il reste '.$fmt($limit - $spent).' '.$until.'.',
            'url' => '/#plafonds',
            'tag' => 'budget-'.$category->id.'-80',
        ];
    }
}
