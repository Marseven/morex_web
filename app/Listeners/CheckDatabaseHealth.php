<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * Étend /up : une exception ici fait répondre 500, ce que repère une surveillance externe.
 */
final class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->select('select 1');
    }
}
