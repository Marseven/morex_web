<?php

namespace App\Console\Commands;

use App\Models\BudgetCycle;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Console\Command;

class DiagnoseActiveBudget extends Command
{
    protected $signature = 'budget:diagnose-active {--email= : Limiter à un utilisateur (email)}';

    protected $description = "Diagnostique le cycle actif : montre, par catégorie, les transactions comptées dans « dépensé ce mois » avec leurs dates";

    public function handle(): int
    {
        $cyclesQuery = BudgetCycle::where('status', 'active')->with('user');
        if ($this->option('email')) {
            $cyclesQuery->whereHas('user', fn ($q) => $q->where('email', $this->option('email')));
        }
        $cycles = $cyclesQuery->get();

        if ($cycles->isEmpty()) {
            $this->warn('Aucun cycle actif.');
            return self::SUCCESS;
        }

        foreach ($cycles as $cycle) {
            $start = $cycle->start_date->format('Y-m-d');
            $this->newLine();
            $this->info("Cycle actif : {$cycle->period_name}  (user: " . ($cycle->user?->email ?? $cycle->user_id) . ")");
            $this->line("  start_date = {$start}" . ($cycle->end_date ? "  end_date = {$cycle->end_date->format('Y-m-d')}" : "  end_date = (aucune, ouvert)"));
            $this->line("  Comptage : transactions type=expense avec date >= {$start}" . ($cycle->end_date ? " et <= {$cycle->end_date->format('Y-m-d')}" : ''));

            // Toutes les dépenses comptées, groupées par catégorie
            $q = Transaction::where('user_id', $cycle->user_id)
                ->where('type', 'expense')
                ->where('date', '>=', $start);
            if ($cycle->end_date) {
                $q->where('date', '<=', $cycle->end_date->format('Y-m-d'));
            }
            $txs = $q->with('category')->orderBy('date')->get();

            if ($txs->isEmpty()) {
                $this->line('  (aucune dépense comptée)');
                continue;
            }

            $rows = $txs->map(fn ($t) => [
                $t->date->format('Y-m-d'),
                $t->category?->name ?? '(sans catégorie)',
                number_format($t->amount, 0, ',', ' '),
                \Illuminate\Support\Str::limit($t->beneficiary ?? $t->description ?? '', 30),
            ])->toArray();

            $this->table(['Date', 'Catégorie', 'Montant', 'Libellé'], $rows);
            $this->line('  TOTAL compté : ' . number_format($txs->sum('amount'), 0, ',', ' ') . ' FCFA sur ' . $txs->count() . ' transaction(s)');

            // Répartition des dates (pour repérer une frontière mal placée)
            $byDate = $txs->groupBy(fn ($t) => $t->date->format('Y-m-d'))
                ->map(fn ($g) => $g->sum('amount'));
            $this->line('  Par date : ' . collect($byDate)->map(fn ($amt, $d) => "$d=" . number_format($amt, 0, ',', ' '))->implode('  '));
        }

        return self::SUCCESS;
    }
}
