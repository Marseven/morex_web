<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bilan d'un cycle et proposition du cycle suivant (module bilan-mensuel 8).
 *
 * RM-1 montants via counted() / personal() / personalBudgetFor() (modules 5c, 5d).
 * RM-2 épargne = revenus − toutes les dépenses comptées ; taux = épargne ÷ revenus.
 * RM-3 proposition par catégorie courante sur les 3 derniers cycles (maintenu, baisse, nouveau, inchangé).
 * RM-4 revenu attendu = moyenne des revenus comptés de ces cycles.
 * RM-5 lecture seule : rien n'est modifié ici, la proposition s'applique au lancement validé.
 */
final class MonthlyReviewService
{
    public const SAVINGS_TARGET_RATE = 25;

    public const HISTORY = 3;

    /** Dépassé au moins ce nombre de fois : plafond maintenu. */
    public const KEEP_AFTER_OVERRUNS = 2;

    /** Toujours sous ce pourcentage du plafond : baisse proposée. */
    public const LOWER_BELOW_PERCENT = 70;

    public function __construct(
        private readonly PlannedPurchaseService $purchases,
        private readonly GoalProgressService $goals,
    ) {}

    /** @return array<string, mixed> */
    public function review(User $user, BudgetCycle $cycle): array
    {
        [$from, $to] = $this->range($cycle);
        $base = fn () => Transaction::where('user_id', $user->id)->counted()->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59']);

        $income = (int) $base()->where('type', 'income')->sum('amount');
        $expense = (int) $base()->where('type', 'expense')->sum('amount');
        $personal = (int) $base()->personal()->where('type', 'expense')->sum('amount');
        $transit = Transaction::where('user_id', $user->id)->ofNature(Category::NATURE_TRANSIT)
            ->where('status', '!=', Transaction::STATUS_PENDING)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

        $categories = $this->categories($user);
        $spent = $this->spentByCategory($user, $from, $to);
        $rows = $this->rows($categories, $spent);
        $savings = $income - $expense;

        return [
            'cycle' => [
                'id' => $cycle->id, 'start' => $from->toDateString(), 'end' => $to->toDateString(),
                'status' => $cycle->status, 'is_latest' => ! BudgetCycle::where('user_id', $user->id)->where('start_date', '>', $cycle->start_date)->exists(),
            ],
            'income' => $income,
            'personal_spent' => $personal,
            'personal_budget' => Category::personalBudgetFor($user->id),
            'pro' => (int) $base()->ofNature(Category::NATURE_PRO)->where('type', 'expense')->sum('amount'),
            'transit_net' => (int) ($transit['income'] ?? 0) - (int) ($transit['expense'] ?? 0),
            'out_of_list' => $this->purchases->outOfListTotals($user, $from, $to),
            'savings' => $savings,
            'savings_rate' => $income > 0 ? round($savings * 100 / $income, 1) : 0.0,
            'savings_target_rate' => self::SAVINGS_TARGET_RATE,
            'goal' => $cycle->status === 'active' ? $this->trackedGoal($user) : null,
            'discretionary' => [
                'spent' => (int) $rows->where('discretionary', true)->sum('spent'),
                'budget' => (int) $rows->where('discretionary', true)->sum('limit'),
            ],
            'categories' => $rows->sortBy([['status_rank', 'asc'], ['diff', 'desc']])->values()->map(fn ($r) => collect($r)->except('status_rank')->all())->all(),
            'proposal' => $this->proposal($user, $cycle, $categories),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} début et fin incluse (aujourd'hui pour un cycle en cours) */
    private function range(BudgetCycle $cycle): array
    {
        $from = CarbonImmutable::parse($cycle->start_date)->startOfDay();
        $to = $cycle->end_date ? CarbonImmutable::parse($cycle->end_date) : CarbonImmutable::today();

        return [$from, $to->max($from)];
    }

    /** Catégories de dépense courantes de l'utilisateur (les catégories système ne sont pas réglables). */
    private function categories(User $user): Collection
    {
        return Category::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('nature', Category::NATURE_REGULAR)
            ->orderBy('order_index')
            ->get(['id', 'name', 'budget_limit', 'discretionary']);
    }

    /** @return Collection<string, int> dépenses perso par catégorie sur la période */
    private function spentByCategory(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Transaction::where('user_id', $user->id)->counted()->personal()
            ->where('type', 'expense')
            ->whereNotNull('category_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->selectRaw('category_id, SUM(amount) as total')->groupBy('category_id')
            ->pluck('total', 'category_id')->map(fn ($v) => (int) $v);
    }

    private function rows(Collection $categories, Collection $spent): Collection
    {
        return $categories->map(function (Category $c) use ($spent) {
            $amount = $spent[$c->id] ?? 0;
            $limit = (int) $c->budget_limit;
            $status = $limit <= 0 ? 'none' : ($amount > $limit ? 'over' : 'ok');

            return [
                'id' => $c->id, 'name' => $c->name, 'discretionary' => (bool) $c->discretionary,
                'spent' => $amount, 'limit' => $limit ?: null, 'diff' => $limit > 0 ? $amount - $limit : null,
                'status' => $status, 'status_rank' => ['over' => 0, 'ok' => 1, 'none' => 2][$status],
            ];
        })->filter(fn ($r) => $r['spent'] > 0 || $r['limit'] !== null)->values();
    }

    /** RM-3 / RM-4 : cycle revu et jusqu'à 2 cycles précédents. */
    private function proposal(User $user, BudgetCycle $cycle, Collection $categories): array
    {
        $history = BudgetCycle::where('user_id', $user->id)
            ->where('start_date', '<=', $cycle->start_date)
            ->orderByDesc('start_date')
            ->limit(self::HISTORY)
            ->get();
        $periods = $history->map(fn (BudgetCycle $c) => $this->range($c));
        $spentPerCycle = $periods->map(fn (array $p) => $this->spentByCategory($user, $p[0], $p[1]));
        $incomes = $periods->map(fn (array $p) => (int) Transaction::where('user_id', $user->id)->counted()->where('type', 'income')
            ->whereBetween('date', [$p[0]->toDateString(), $p[1]->toDateString().' 23:59:59'])->sum('amount'));
        $hasHistory = $history->count() > 1 || $incomes->sum() > 0;

        $lines = $categories->map(fn (Category $c) => $this->proposeFor($c, $spentPerCycle->map(fn ($s) => $s[$c->id] ?? 0), $hasHistory))
            ->filter()->values();
        $expectedIncome = $hasHistory ? (int) (round($incomes->avg() / 1000) * 1000) : 0;
        $savingsTarget = (int) Goal::where('user_id', $user->id)->where('status', 'active')->sum('monthly_target');
        $total = (int) $lines->sum('proposed');

        return [
            'has_history' => $hasHistory,
            'cycles' => $history->count(),
            'expected_income' => $expectedIncome,
            'savings_target' => $savingsTarget,
            'total' => $total,
            'over_budget' => $hasHistory && $total > $expectedIncome - $savingsTarget,
            'categories' => $lines->all(),
        ];
    }

    /** @param Collection<int, int> $spent dépenses par cycle, du plus récent au plus ancien */
    private function proposeFor(Category $category, Collection $spent, bool $hasHistory): ?array
    {
        $limit = (int) $category->budget_limit;
        $count = $spent->count();
        $average = $count > 0 ? $spent->avg() : 0;
        $line = fn (int $proposed, string $rule, string $reason) => [
            'id' => $category->id, 'name' => $category->name, 'current' => $limit ?: null,
            'proposed' => $proposed, 'rule' => $rule, 'reason' => $reason,
        ];

        if ($limit <= 0) {
            return $hasHistory && $average > 0
                ? $line((int) (ceil($average / 1000) * 1000), 'new', "Jamais plafonnée : moyenne des {$count} derniers cycles")
                : null;
        }

        $overruns = $spent->filter(fn (int $s) => $s > $limit)->count();
        if ($overruns >= self::KEEP_AFTER_OVERRUNS) {
            return $line($limit, 'kept', "Dépassé {$overruns} cycles sur {$count} : plafond maintenu");
        }

        if ($hasHistory && $spent->every(fn (int $s) => $s * 100 < $limit * self::LOWER_BELOW_PERCENT)) {
            return $line((int) (ceil($average / 1000) * 1000), 'lowered', 'Sous '.self::LOWER_BELOW_PERCENT." % depuis {$count} cycles : baisse proposée");
        }

        return $line($limit, 'unchanged', 'Plafond inchangé');
    }

    /** Versement du cycle vers l'objectif suivi (module 5a), seulement pour le cycle en cours. */
    private function trackedGoal(User $user): ?array
    {
        $goal = Goal::where('user_id', $user->id)->where('status', 'active')->whereNotNull('tracked_since')->first();

        return $goal ? $this->goals->status($goal) : null;
    }
}
