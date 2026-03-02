<?php

namespace App\Console\Commands;

use App\Models\BudgetClosure;
use App\Models\BudgetCycle;
use Illuminate\Console\Command;

class FixBudgetClosures extends Command
{
    protected $signature = 'budget:fix-closures
                            {--show : Afficher les closures sans les corriger}
                            {--delete-wrong : Supprimer les closures avec le mauvais mois}
                            {--recreate : Recréer les closures depuis les cycles clôturés}';

    protected $description = 'Corriger les budget_closures avec le mauvais mois (bug start_date vs end_date)';

    public function handle(): int
    {
        $this->info('=== État actuel des closures ===');
        $this->newLine();

        $closures = BudgetClosure::orderBy('year', 'desc')->orderBy('month', 'desc')->get();

        if ($closures->isEmpty()) {
            $this->warn('Aucun closure trouvé.');
            return 0;
        }

        $headers = ['ID', 'Année-Mois', 'Revenus', 'Dépenses', 'Créé le'];
        $rows = [];

        foreach ($closures as $closure) {
            $rows[] = [
                substr($closure->id, 0, 8) . '...',
                sprintf('%04d-%02d', $closure->year, $closure->month),
                number_format($closure->total_income, 0, ',', ' '),
                number_format($closure->total_spent, 0, ',', ' '),
                $closure->created_at->format('Y-m-d'),
            ];
        }

        $this->table($headers, $rows);

        if ($this->option('show')) {
            return 0;
        }

        if ($this->option('delete-wrong')) {
            $this->newLine();
            $this->warn('Mode DELETE: Supprimer les closures incorrects');

            $idToDelete = $this->ask('ID du closure à supprimer (premiers caractères suffisent)');
            $closure = BudgetClosure::where('id', 'like', $idToDelete . '%')->first();

            if (!$closure) {
                $this->error("Closure non trouvé avec ID: {$idToDelete}");
                return 1;
            }

            if ($this->confirm("Supprimer le closure {$closure->year}-{$closure->month} ?")) {
                $closure->delete();
                $this->info('Closure supprimé avec succès.');
            }

            return 0;
        }

        if ($this->option('recreate')) {
            $this->newLine();
            $this->warn('Mode RECREATE: Recréer les closures depuis les cycles clôturés');
            $this->newLine();

            $closedCycles = BudgetCycle::where('status', 'closed')
                ->whereNotNull('end_date')
                ->orderBy('start_date')
                ->get();

            if ($closedCycles->isEmpty()) {
                $this->warn('Aucun cycle clôturé trouvé.');
                return 0;
            }

            $this->info("Cycles clôturés trouvés: {$closedCycles->count()}");
            $this->newLine();

            foreach ($closedCycles as $cycle) {
                $this->line("Cycle: {$cycle->period_name} ({$cycle->start_date->format('Y-m-d')} -> {$cycle->end_date->format('Y-m-d')})");

                // Supprimer l'ancien closure si existe
                $closureMonth = $cycle->end_date->month;
                $closureYear = $cycle->end_date->year;

                BudgetClosure::where('user_id', $cycle->user_id)
                    ->where('year', $closureYear)
                    ->where('month', $closureMonth)
                    ->delete();

                // Recréer via la méthode du modèle
                $newClosure = $cycle->createClosure();

                $this->info("  → Closure créé: {$newClosure->year}-{$newClosure->month} (Revenus: {$newClosure->total_income}, Dépenses: {$newClosure->total_spent})");
            }

            $this->newLine();
            $this->info('Closures recréés avec succès.');
            return 0;
        }

        $this->newLine();
        $this->line('Options disponibles:');
        $this->line('  --show           : Afficher seulement');
        $this->line('  --delete-wrong   : Supprimer un closure incorrect');
        $this->line('  --recreate       : Recréer tous les closures depuis les cycles clôturés');
        $this->newLine();

        return 0;
    }
}
