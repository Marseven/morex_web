<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappel quotidien de saisie (Web Push) à 20h, fuseau de l'app.
Schedule::command('reminders:daily')
    ->dailyAt('20:00')
    ->timezone(config('app.timezone'));

// Sauvegarde MySQL nocturne (rotation 14 jours), voir docs/runbook.md.
Schedule::command('backup:database')
    ->dailyAt('03:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
