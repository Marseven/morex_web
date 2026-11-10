<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * socle-prod RM-2 / RM-3 : sauvegarde quotidienne compressée, conservée 14 jours,
 * sans jamais exposer le mot de passe de la base.
 */
class BackupDatabaseCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config([
            'backup.path' => $this->dir,
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'morex',
            'database.connections.mysql.username' => 'morex_user',
            'database.connections.mysql.password' => 'p@ss"word',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** mysqldump simulé : écrit le fichier demandé par --result-file et mémorise la commande. */
    private function fakeDump(int $exitCode = 0): \ArrayObject
    {
        $seen = new \ArrayObject;

        Process::fake(function (PendingProcess $process) use ($seen, $exitCode) {
            $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
            $seen['command'] = $command;

            preg_match('/--defaults-extra-file=(\S+)/', $command, $defaults);
            $seen['defaults'] = $defaults[1] ?? null;
            $seen['defaults_content'] = isset($defaults[1]) && is_file($defaults[1]) ? file_get_contents($defaults[1]) : null;

            if ($exitCode === 0 && preg_match('/--result-file=(\S+)/', $command, $result)) {
                file_put_contents($result[1], "-- dump morex\nCREATE TABLE accounts (id char(36));\n");
            }

            return Process::result(exitCode: $exitCode);
        });

        return $seen;
    }

    /** @return list<string> */
    private function backups(): array
    {
        return glob($this->dir.'/morex-*.sql.gz') ?: [];
    }

    public function test_it_writes_a_compressed_dump(): void
    {
        $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertCount(1, $this->backups());
        $this->assertStringContainsString('CREATE TABLE accounts', gzdecode(file_get_contents($this->backups()[0])));
        $this->assertSame([], glob($this->dir.'/*.sql') ?: []);
    }

    public function test_the_password_never_appears_on_the_command_line(): void
    {
        $seen = $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertStringNotContainsString('p@ss', $seen['command']);
        $this->assertStringContainsString('password="p@ss\"word"', (string) $seen['defaults_content']);
        $this->assertFileDoesNotExist($seen['defaults']);
    }

    public function test_it_deletes_backups_older_than_fourteen_days(): void
    {
        $this->fakeDump();
        File::ensureDirectoryExists($this->dir);
        touch($old = $this->dir.'/morex-20260101-0300.sql.gz', now()->subDays(15)->getTimestamp());
        touch($recent = $this->dir.'/morex-20261101-0300.sql.gz', now()->subDays(9)->getTimestamp());

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    public function test_a_failed_dump_leaves_no_partial_file_and_is_logged(): void
    {
        $this->fakeDump(exitCode: 2);
        Log::spy();

        $this->artisan('backup:database')->assertFailed();

        $this->assertSame([], $this->backups());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'BACKUP_FAILED')->once();
    }

    public function test_it_refuses_a_non_mysql_connection(): void
    {
        config(['database.default' => 'sqlite']);
        Process::fake();

        $this->artisan('backup:database')->assertFailed();

        Process::assertNothingRan();
    }

    public function test_it_is_scheduled_every_night(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'backup:database'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('passwordsWithControlCharacters')]
    public function test_it_refuses_a_password_that_would_break_the_options_file(string $password): void
    {
        config(['database.connections.mysql.password' => $password]);
        Process::fake();

        $this->artisan('backup:database')->assertFailed();

        Process::assertNothingRan();
    }

    /** @return array<string, array{string}> */
    public static function passwordsWithControlCharacters(): array
    {
        return [
            'retour à la ligne' => ["abc\nplugin-dir=/tmp"],
            'retour chariot' => ["abc\rdef"],
            'octet nul' => ["abc\0def"],
        ];
    }

    public function test_backup_names_carry_a_random_suffix(): void
    {
        $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertMatchesRegularExpression('/morex-\d{8}-\d{6}-[0-9a-f]{12}\.sql\.gz$/', $this->backups()[0]);
    }

    public function test_backup_files_are_private(): void
    {
        $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $this->assertSame('600', substr(sprintf('%o', fileperms($this->backups()[0])), -3));
        $this->assertSame('700', substr(sprintf('%o', fileperms($this->dir)), -3));
    }
}
