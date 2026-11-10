<?php

declare(strict_types=1);

return [
    // Hors de public/ : jamais servi par le serveur web.
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Chemin de mysqldump si le binaire n'est pas dans le PATH du cron.
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

    'timeout' => (int) env('BACKUP_TIMEOUT', 300),
];
