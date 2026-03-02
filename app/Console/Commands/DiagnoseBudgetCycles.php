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

            // Vérifier si end_date est cohérent avec period_name
            // Ex: "Février 2026" doit avoir end_date en février
            if (preg_match('/(\w+)\s+(\d{4})/', $cycle->period_name, $matches)) {
                $monthName = $matches[1];
                $yearName = (int) $matches[2];

                $monthMap = [
                    'Janvier' => 1, 'Février' => 2, 'Mars' => 3, 'Avril' => 4,
                    'Mai' => 5, 'Juin' => 6, 'Juillet' => 7, 'Août' => 8,
                    'Septembre' => 9, 'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
                ];

                $expectedMonth = $monthMap[$monthName] ?? null;

                if ($expectedMonth && $cycle->end_date->month !== $expectedMonth) {
                    $this->warn("  ⚠️  end_date ({$cycle->end_date->format('Y-m-d')}) ne correspond pas au nom '{$cycle->period_name}' !");

                    // Calculer le dernier jour du mois attendu
                    $correctEndDate = \Carbon\Carbon::create($yearName, $expectedMonth, 1)->endOfMonth();
                    $this->line("  → Devrait être: {$correctEndDate->format('Y-m-d')} (dernier jour de {$monthName})");

                    $problems[] = [
                        'cycle' => $cycle,
                        'correct_end_date' => $correctEndDate,
                    ];
                }
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
