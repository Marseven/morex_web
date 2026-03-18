<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BalanceHistory;
use App\Models\User;
use Illuminate\Console\Command;

class SyncAccountBalances extends Command
{
    protected $signature = 'accounts:sync-balances
                            {--user= : ID ou email de l\'utilisateur}
                            {--show : Afficher les soldes et le dernier mouvement}
                            {--reset : Mettre initial_balance = balance actuel (figer les soldes)}
                            {--verify : Comparer le solde du compte avec le dernier balance_after du journal}';

    protected $description = 'Synchroniser et vérifier les soldes des comptes';

    public function handle(): int
    {
        $userOption = $this->option('user');

        if ($userOption) {
            $user = is_numeric($userOption)
                ? User::find($userOption)
                : User::where('email', $userOption)->first();

            if (!$user) {
                $this->error("Utilisateur non trouvé: {$userOption}");
                return 1;
            }

            $this->processUser($user);
        } else {
            // Tous les utilisateurs
            User::all()->each(fn($user) => $this->processUser($user));
        }

        return 0;
    }

    private function processUser(User $user): void
    {
        $this->info("Utilisateur: {$user->name} ({$user->email})");
        $this->newLine();

        $accounts = Account::where('user_id', $user->id)->get();

        if ($accounts->isEmpty()) {
            $this->warn("  Aucun compte trouvé.");
            return;
        }

        $fmt = fn($v) => number_format($v, 0, ',', ' ');

        if ($this->option('verify')) {
            $this->verifyBalances($accounts, $fmt);
            return;
        }

        $headers = ['Compte', 'Solde DB', 'Dernier mouvement', 'Date'];
        $rows = [];

        foreach ($accounts as $account) {
            $lastEntry = BalanceHistory::where('account_id', $account->id)
                ->orderByDesc('created_at')
                ->first();

            $lastMovement = $lastEntry
                ? "{$lastEntry->type} ({$fmt($lastEntry->amount)})"
                : 'Aucun';
            $lastDate = $lastEntry?->created_at?->format('d/m/Y H:i') ?? '-';

            $rows[] = [
                $account->name,
                $fmt($account->balance),
                $lastMovement,
                $lastDate,
            ];
        }

        $this->table($headers, $rows);
        $this->line("  Total: {$fmt($accounts->sum('balance'))} FCFA");

        if ($this->option('show')) {
            return;
        }

        if ($this->option('reset')) {
            $this->newLine();
            $this->warn('Mode RESET: Les soldes initiaux vont être mis à jour.');
            $this->line('  Entrez un montant absolu (ex: 500000) ou relatif (ex: +5000, -3000)');
            $this->newLine();

            foreach ($accounts as $account) {
                $currentBalance = $account->balance;
                $this->line("  Compte: {$account->name} (solde actuel: " . number_format($currentBalance, 0, ',', ' ') . " FCFA)");

                $input = $this->ask(
                    "    Nouveau solde réel (ou +/- pour ajuster)",
                    (string) $currentBalance
                );

                $trimmed = trim((string) $input);

                if (!is_numeric($trimmed)) {
                    $this->error("    ✗ Valeur invalide: {$trimmed} (ignoré)");
                    continue;
                }

                // Relative adjustment (+5000 or -3000) vs absolute value
                if (str_starts_with($trimmed, '+') || str_starts_with($trimmed, '-')) {
                    $newBalance = $currentBalance + (int) $trimmed;
                    $this->line("    Ajustement: " . number_format($currentBalance, 0, ',', ' ') . " " . ($trimmed[0] === '+' ? '+' : '') . number_format((int) $trimmed, 0, ',', ' '));
                } else {
                    $newBalance = (int) $trimmed;
                }

                $account->adjustToBalance($newBalance);

                $this->info("    → Solde mis à jour: " . number_format($newBalance, 0, ',', ' ') . " FCFA");
                $this->newLine();
            }

            $this->info('Soldes synchronisés avec succès.');
        } else {
            $this->newLine();
            $this->line('Options disponibles:');
            $this->line('  --show     : Afficher seulement (aucune modification)');
            $this->line('  --verify   : Vérifier la cohérence entre solde et journal');
            $this->line('  --reset    : Entrer manuellement les vrais soldes');
            $this->newLine();
        }
    }

    /**
     * Vérifie la cohérence entre le solde du compte et le dernier balance_after du journal.
     */
    private function verifyBalances($accounts, callable $fmt): void
    {
        $this->info('Vérification de la cohérence des soldes...');
        $this->newLine();

        $headers = ['Compte', 'Solde DB', 'Dernier journal', 'Écart', 'Statut'];
        $rows = [];
        $hasErrors = false;

        foreach ($accounts as $account) {
            $lastEntry = BalanceHistory::where('account_id', $account->id)
                ->orderByDesc('created_at')
                ->first();

            $journalBalance = $lastEntry?->balance_after;
            $ecart = $journalBalance !== null ? $account->balance - $journalBalance : null;

            $status = '✓';
            if ($journalBalance === null) {
                $status = '⚠ Pas de journal';
                $hasErrors = true;
            } elseif ($ecart !== 0) {
                $status = '✗ ÉCART';
                $hasErrors = true;
            }

            $rows[] = [
                $account->name,
                $fmt($account->balance),
                $journalBalance !== null ? $fmt($journalBalance) : '-',
                $ecart !== null && $ecart !== 0 ? $fmt($ecart) : ($journalBalance !== null ? '0' : '-'),
                $status,
            ];
        }

        $this->table($headers, $rows);

        if ($hasErrors) {
            $this->warn('Des incohérences ont été détectées.');
        } else {
            $this->info('Tous les soldes sont cohérents avec le journal.');
        }
    }
}
