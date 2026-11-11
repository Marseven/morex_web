<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurrenceGenerator;
use Illuminate\Console\Command;

/** Génère les opérations récurrentes échues (module recurrences-serveur), planifiée chaque matin. */
final class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurrences:generate';

    protected $description = 'Génère les opérations récurrentes échues, rattrapage compris (idempotent)';

    public function handle(RecurrenceGenerator $generator): int
    {
        $count = $generator->runAll();
        $this->info("{$count} opération(s) générée(s).");

        return self::SUCCESS;
    }
}
