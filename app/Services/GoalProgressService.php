<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\BalanceHistory;
use App\Models\BudgetCycle;
use App\Models\Goal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Objectif d'épargne suivi sur un compte (module suivi-objectifs 5a).
 *
 * RM-2 versement du mois = variation du solde du compte depuis le début du cycle actif (sinon du mois civil) ;
 * RM-3 projection = reste ÷ mois restants jusqu'à la date cible.
 */
final class GoalProgressService
{
    /** Au-delà de ce jour du cycle, un versement incomplet est « en retard ». */
    public const LATE_AFTER_DAYS = 10;

    public function track(Goal $goal, Account $account, int $startAmount, ?int $monthlyTarget): Goal
    {
        $goal->forceFill([
            'account_id' => $account->id,
            'tracked_since' => now()->toDateString(),
            'tracked_start_amount' => $startAmount,
            'tracked_account_balance' => $account->balance,
            'monthly_target' => $monthlyTarget,
        ])->save();

        return $goal;
    }

    /** Délier : la progression redevient manuelle, figée à sa dernière valeur calculée. */
    public function untrack(Goal $goal): Goal
    {
        $frozen = $goal->current_amount;
        $goal->forceFill([
            'tracked_since' => null,
            'tracked_start_amount' => null,
            'tracked_account_balance' => null,
            'current_amount' => $frozen,
        ])->save();

        return $goal;
    }

    public function assertManual(Goal $goal): void
    {
        if ($goal->isTracked()) {
            throw ValidationException::withMessages(['amount' => ['TRACKED_GOAL : la progression de cet objectif est calculée à partir de son compte.']]);
        }
    }

    /** @return array<string, mixed> */
    public function status(Goal $goal): array
    {
        $current = $goal->current_amount;
        $remaining = max(0, $goal->target_amount - $current);
        [$periodStart, $isCycle] = $this->period($goal);

        $paid = $goal->isTracked() ? $this->balanceDelta($goal->account_id, $periodStart) : null;
        $monthly = $goal->monthly_target;
        $monthStatus = match (true) {
            $paid === null || $monthly === null || $monthly === 0 => 'none',
            $paid >= $monthly => 'achieved',
            $periodStart->diffInDays(now()) > self::LATE_AFTER_DAYS => 'late',
            default => 'remaining',
        };

        $required = $this->requiredPerMonth($goal, $remaining);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'current_amount' => $current,
            'target_amount' => $goal->target_amount,
            'percent' => $goal->target_amount > 0 ? min(100, (int) floor($current * 100 / $goal->target_amount)) : 0,
            'target_date' => $goal->target_date?->toDateString(),
            'is_tracked' => $goal->isTracked(),
            'is_achieved' => $current >= $goal->target_amount,
            'period_start' => $periodStart->toDateString(),
            'period_is_cycle' => $isCycle,
            'month_paid' => $paid,
            'month_target' => $monthly,
            'month_status' => $monthStatus,
            'month_remaining' => $paid !== null && $monthly !== null ? max(0, $monthly - $paid) : null,
            'required_per_month' => $required,
            'on_track' => $required === null ? null : ($monthly !== null && $monthly >= $required),
        ];
    }

    /** @return array{CarbonImmutable, bool} début de la période et « est un cycle budgétaire » */
    private function period(Goal $goal): array
    {
        $cycle = BudgetCycle::where('user_id', $goal->user_id)->where('status', 'active')->latest('start_date')->first();

        return $cycle !== null
            ? [CarbonImmutable::parse($cycle->start_date)->startOfDay(), true]
            : [CarbonImmutable::now()->startOfMonth(), false];
    }

    /** Variation du solde depuis une date, lue dans le journal des soldes. */
    private function balanceDelta(string $accountId, CarbonImmutable $since): int
    {
        $now = (int) Account::withTrashed()->whereKey($accountId)->value('balance');

        $before = BalanceHistory::where('account_id', $accountId)->where('created_at', '<', $since)->latest('created_at')->value('balance_after')
            ?? BalanceHistory::where('account_id', $accountId)->where('created_at', '>=', $since)->oldest('created_at')->value('balance_before')
            ?? $now;

        return $now - (int) $before;
    }

    private function requiredPerMonth(Goal $goal, int $remaining): ?int
    {
        if ($goal->target_date === null || $remaining === 0) {
            return $goal->target_date === null ? null : 0;
        }
        $months = max(1, (int) CarbonImmutable::now()->startOfMonth()->diffInMonths(CarbonImmutable::parse($goal->target_date)->startOfMonth()) + 1);

        return (int) ceil($remaining / $months);
    }
}
