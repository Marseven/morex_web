<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use Illuminate\Console\Command;

class SyncAccountBalances extends Command
{
    protected $signature = 'accounts:sync-balances
                            {--user= : ID ou email de l\'utilisateur}
                            {--show : Afficher les soldes sans modifier}
                            {--reset : Mettre initial_balance = balance actuel (figer les soldes)}';

    protected $description = 'Synchroniser les soldes des comptes';

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

        $headers = ['Compte', 'Solde Initial', 'Solde Calculé', 'Écart'];
        $rows = [];

        foreach ($accounts as $account) {
            // Calculer le solde basé sur les transactions
            $income = $account->transactions()->where('type', 'income')->sum('amount');
            $expense = $account->transactions()->where('type', 'expense')->sum('amount');
            $transfersOut = $account->transactions()->where('type', 'transfer')->sum('amount');
            $transfersIn = $account->incomingTransfers()->sum('amount');

            $calculatedBalance = $account->initial_balance + $income - $expense - $transfersOut + $transfersIn;
            $ecart = $account->balance - $calculatedBalance;

            $rows[] = [
                $account->name,
                number_format($account->initial_balance, 0, ',', ' '),
                number_format($calculatedBalance, 0, ',', ' '),
                $ecart != 0 ? number_format($ecart, 0, ',', ' ') : '✓',
            ];
        }

        $this->table($headers, $rows);

        if ($this->option('show')) {
            return;
        }

        if ($this->option('reset')) {
            $this->newLine();
            $this->warn('Mode RESET: Les soldes initiaux vont être mis à jour.');
            $this->newLine();

            foreach ($accounts as $account) {
                $this->line("  Compte: {$account->name}");
                $newInitialBalance = $this->ask(
                    "    Nouveau solde réel pour '{$account->name}' (actuel: " . number_format($account->balance, 0, ',', ' ') . ")",
                    $account->balance
                );

                $account->update([
                    'initial_balance' => (int) $newInitialBalance,
                    'balance' => (int) $newInitialBalance,
                ]);

                $this->info("    → Solde mis à jour: " . number_format($newInitialBalance, 0, ',', ' ') . " FCFA");
            }

            $this->newLine();
            $this->info('Soldes synchronisés avec succès.');
        } else {
            $this->newLine();
            $this->line('Options disponibles:');
            $this->line('  --show   : Afficher seulement (aucune modification)');
            $this->line('  --reset  : Entrer manuellement les vrais soldes');
            $this->newLine();
        }
    }
}
