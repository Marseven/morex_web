<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Sauvegarde MySQL compressée avec rotation, pensée pour un hébergement mutualisé.
 *
 * Le mot de passe passe par un fichier d'options temporaire (0600) et jamais par la ligne de
 * commande, visible dans la liste des processus du serveur.
 */
final class DatabaseBackup
{
    /** @return string chemin du fichier .sql.gz créé */
    public function run(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Sauvegarde possible uniquement sur une connexion MySQL/MariaDB.');
        }

        $directory = config('backup.path');
        File::ensureDirectoryExists($directory, 0700);

        $base = $directory.'/morex-'.now()->format('Ymd-His');
        $defaultsFile = $this->writeDefaultsFile($connection);

        try {
            $this->dump($connection['database'], $defaultsFile, $base.'.sql');
            $this->compress($base.'.sql', $base.'.sql.gz');
        } finally {
            File::delete([$defaultsFile, $base.'.sql']);
        }

        return $base.'.sql.gz';
    }

    /** @return int nombre de sauvegardes supprimées */
    public function prune(): int
    {
        $limit = now()->subDays(config('backup.keep_days'))->getTimestamp();
        $deleted = 0;

        foreach (glob(config('backup.path').'/morex-*.sql.gz') ?: [] as $file) {
            if (filemtime($file) < $limit && File::delete($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function dump(string $database, string $defaultsFile, string $target): void
    {
        $result = Process::timeout(config('backup.timeout'))->run([
            config('backup.mysqldump'),
            '--defaults-extra-file='.$defaultsFile,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--result-file='.$target,
            $database,
        ]);

        if ($result->failed() || ! is_file($target)) {
            // Code de sortie seulement : la sortie d'erreur de mysqldump peut contenir l'hôte et l'utilisateur.
            Log::error('BACKUP_FAILED', ['exit_code' => $result->exitCode()]);

            throw new RuntimeException('mysqldump a échoué (code '.$result->exitCode().').');
        }
    }

    private function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
        chmod($target, 0600);
    }

    /** @param  array<string, mixed>  $connection */
    private function writeDefaultsFile(array $connection): string
    {
        $file = tempnam(sys_get_temp_dir(), 'morex-backup-');
        chmod($file, 0600);

        $lines = ['[client]'];
        foreach (['user' => 'username', 'password' => 'password', 'host' => 'host', 'port' => 'port', 'socket' => 'unix_socket'] as $option => $key) {
            if (filled($connection[$key] ?? null)) {
                $lines[] = $option.'="'.addcslashes((string) $connection[$key], '"\\').'"';
            }
        }

        file_put_contents($file, implode("\n", $lines)."\n");

        return $file;
    }
}
