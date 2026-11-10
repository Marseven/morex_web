<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use RuntimeException;

final class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Sauvegarde compressée de la base MySQL, avec rotation (config/backup.php)';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $file = $backup->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sauvegarde : '.basename($file).' ('.round(filesize($file) / 1024).' Ko)');
        $this->line($backup->prune().' sauvegarde(s) expirée(s) supprimée(s).');

        return self::SUCCESS;
    }
}
