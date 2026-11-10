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

        $defaultsFile = $this->writeDefaultsFile($connection);

        $directory = config('backup.path');
        File::ensureDirectoryExists($directory, 0700);
        chmod($directory, 0700);

        // Suffixe aléatoire : le nom d'une sauvegarde ne se devine pas à partir de l'heure du cron.
        $base = $directory.'/morex-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6));

        try {
            $this->dump($connection['database'], $defaultsFile, $base.'.sql');
            $this->compress($base.'.sql', $base.'.sql.gz');
        } catch (\Throwable $e) {
            File::delete($base.'.sql.gz');

            throw $e instanceof RuntimeException ? $e : new RuntimeException('Compression de la sauvegarde impossible.', 0, $e);
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
        touch($target);
        chmod($target, 0600);

        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        if ($in === false || $out === false) {
            throw new RuntimeException('Impossible d\'ouvrir les fichiers de sauvegarde.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
    }

    /** @param  array<string, mixed>  $connection */
    private function writeDefaultsFile(array $connection): string
    {
        $lines = ['[client]'];
        foreach (['user' => 'username', 'password' => 'password', 'host' => 'host', 'port' => 'port', 'socket' => 'unix_socket'] as $option => $key) {
            if (filled($connection[$key] ?? null)) {
                $value = (string) $connection[$key];

                // Un retour à la ligne ou un NUL couperait la valeur et injecterait une option [client].
                if (preg_match('/[\r\n\0]/', $value)) {
                    throw new RuntimeException("Valeur de connexion « {$option} » invalide pour mysqldump (caractère de contrôle).");
                }

                $lines[] = $option.'="'.addcslashes($value, '"\\').'"';
            }
        }

        $file = tempnam(sys_get_temp_dir(), 'morex-backup-');
        chmod($file, 0600);

        file_put_contents($file, implode("\n", $lines)."\n");

        return $file;
    }
}
