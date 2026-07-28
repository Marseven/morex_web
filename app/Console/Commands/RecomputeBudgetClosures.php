<?php

namespace App\Console\Commands;

use App\Models\BudgetClosure;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecomputeBudgetClosures extends Command
{
    protected $signature = 'budget:recompute-closures {--dry-run : Afficher les changements sans les enregistrer}';

    protected $description = 'Recalcule les clôtures budgétaires existantes sur la base des transactions du MOIS CALENDAIRE (revenus, dépenses, épargne = revenus − dépenses)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $closures = BudgetClosure::orderBy('user_id')->orderBy('year')->orderBy('month')->get();

        if ($closures->isEmpty()) {
            $this->info('Aucune clôture à recalculer.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Recalcul de {$closures->count()} clôture(s)…");

        $rows = [];
        foreach ($closures as $closure) {
            $start = Carbon::create($closure->year, $closure->month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $income = (int) Transaction::where('user_id', $closure->user_id)
                ->where('type', 'income')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $spent = (int) Transaction::where('user_id', $closure->user_id)
                ->where('type', 'expense')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $budget = (int) Category::where(function ($q) use ($closure) {
                    $q->where('user_id', $closure->user_id)->orWhereNull('user_id');
                })
                ->where('type', 'expense')
                ->whereNotNull('budget_limit')
                ->sum('budget_limit');

            $saved = $income - $spent;

            $rows[] = [
                "{$closure->year}-" . str_pad($closure->month, 2, '0', STR_PAD_LEFT),
                number_format($closure->total_income, 0, ',', ' ') . ' → ' . number_format($income, 0, ',', ' '),
                number_format($closure->total_spent, 0, ',', ' ') . ' → ' . number_format($spent, 0, ',', ' '),
                number_format($closure->total_saved, 0, ',', ' ') . ' → ' . number_format($saved, 0, ',', ' '),
            ];

            if (!$dryRun) {
                $closure->update([
                    'total_income' => $income,
                    'total_spent' => $spent,
                    'total_budget' => $budget,
                    'total_saved' => $saved,
                ]);
            }
        }

        $this->table(['Période', 'Revenus', 'Dépenses', 'Épargne'], $rows);
        $this->info($dryRun ? 'Aucune modification enregistrée (--dry-run).' : 'Clôtures recalculées.');

        return self::SUCCESS;
    }
}
