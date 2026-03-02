<?php

namespace App\Console\Commands;

use App\Models\BudgetCycle;
use App\Models\Transaction;
use Illuminate\Console\Command;

class DiagnoseBudgetCycles extends Command
{
    protected $signature = 'budget:diagnose
                            {--fix : Corriger les end_date incorrects}';

    protected $description = 'Diagnostiquer les cycles budgétaires et leurs transactions';

    public function handle(): int
    {
        $cycles = BudgetCycle::where('status', 'closed')
            ->whereNotNull('end_date')
            ->orderBy('start_date')
            ->get();

        if ($cycles->isEmpty()) {
            $this->warn('Aucun cycle clôturé trouvé.');
            return 0;
        }

        $this->info('=== Diagnostic des cycles clôturés ===');
        $this->newLine();

        $problems = [];

        foreach ($cycles as $cycle) {
            $this->line("Cycle: {$cycle->period_name}");
            $this->line("  Start: {$cycle->start_date->format('Y-m-d')}");
            $this->line("  End:   {$cycle->end_date->format('Y-m-d')}");

            // Vérifier si end_date pointe sur le mois suivant
            $expectedEndMonth = $cycle->start_date->month;
            $actualEndMonth = $cycle->end_date->month;

            if ($actualEndMonth != $expectedEndMonth && $actualEndMonth == $cycle->start_date->copy()->addMonth()->month) {
                $this->warn("  ⚠️  end_date pointe sur le mois suivant !");
                $correctEndDate = $cycle->end_date->copy()->subDay();
                $this->line("  → Devrait être: {$correctEndDate->format('Y-m-d')}");

                $problems[] = [
                    'cycle' => $cycle,
                    'correct_end_date' => $correctEndDate,
                ];
            }

            // Compter les transactions du cycle
            $transactionsCount = $cycle->transactions()->count();
            $expensesTotal = $cycle->transactions()->where('type', 'expense')->sum('amount');
            $incomeTotal = $cycle->transactions()->where('type', 'income')->sum('amount');

            $this->line("  Transactions: {$transactionsCount}");
            $this->line("  Revenus: " . number_format($incomeTotal, 0, ',', ' '));
            $this->line("  Dépenses: " . number_format($expensesTotal, 0, ',', ' '));

            // Transactions du jour end_date (qui ne devraient pas être incluses)
            $transOnEndDate = Transaction::where('user_id', $cycle->user_id)
                ->where('date', $cycle->end_date)
                ->get();

            if ($transOnEndDate->isNotEmpty()) {
                $expenseOnEndDate = $transOnEndDate->where('type', 'expense')->sum('amount');
                if ($expenseOnEndDate > 0) {
                    $this->error("  ❌ Transactions du {$cycle->end_date->format('d/m')} incluses: " . number_format($expenseOnEndDate, 0, ',', ' ') . " FCFA");
                }
            }

            $this->newLine();
        }

        if (empty($problems)) {
            $this->info('✅ Aucun problème détecté.');
            return 0;
        }

        $this->warn("Problèmes détectés: " . count($problems));
        $this->newLine();

        if ($this->option('fix')) {
            foreach ($problems as $problem) {
                $cycle = $problem['cycle'];
                $correctEndDate = $problem['correct_end_date'];

                $this->line("Correction de '{$cycle->period_name}'...");
                $cycle->end_date = $correctEndDate;
                $cycle->save();

                // Recalculer et recréer le closure
                $cycle->updateTotals();
                $cycle->createClosure();

                $this->info("  ✅ Corrigé: end_date = {$correctEndDate->format('Y-m-d')}");
            }

            $this->newLine();
            $this->info('Corrections appliquées avec succès.');
        } else {
            $this->newLine();
            $this->line('Pour corriger automatiquement, relance avec --fix');
        }

        return 0;
    }
}
