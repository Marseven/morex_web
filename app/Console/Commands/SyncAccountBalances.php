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
                            {--reset : Mettre initial_balance = balance actuel (figer les soldes)}
                            {--recalculate : Recalculer automatiquement tous les soldes}';

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

        $headers = ['Compte', 'Solde Initial', 'Revenus', 'Dépenses', 'Transf. ↑', 'Transf. ↓', 'Solde Calculé', 'Solde DB', 'Écart'];
        $rows = [];
        $fmt = fn($v) => number_format($v, 0, ',', ' ');

        foreach ($accounts as $account) {
            // Calculer le solde basé sur les transactions (income/expense uniquement)
            $confirmedTransactions = $account->transactions()->where('status', 'confirmed');
            $income = (clone $confirmedTransactions)->where('type', 'income')->sum('amount');
            $expense = (clone $confirmedTransactions)->where('type', 'expense')->sum('amount');

            // Transferts depuis la table transfers
            $transfersOut = $account->outgoingTransfers()->sum('amount');
            $transfersIn = $account->incomingTransfers()->sum('amount');

            $calculatedBalance = $account->initial_balance + $income - $expense - $transfersOut + $transfersIn;
            $ecart = $account->balance - $calculatedBalance;

            $rows[] = [
                $account->name,
                $fmt($account->initial_balance),
                $income ? '+' . $fmt($income) : '0',
                $expense ? '-' . $fmt($expense) : '0',
                $transfersIn ? '+' . $fmt($transfersIn) : '0',
                $transfersOut ? '-' . $fmt($transfersOut) : '0',
                $fmt($calculatedBalance),
                $fmt($account->balance),
                $ecart != 0 ? $fmt($ecart) : '✓',
            ];
        }

        $this->table($headers, $rows);
        $this->line('  Solde Calculé = Initial + Revenus - Dépenses + Transf.↑ - Transf.↓');
        $this->line('  Écart = Solde DB - Solde Calculé');

        if ($this->option('show')) {
            return;
        }

        if ($this->option('recalculate')) {
            $this->newLine();
            $this->info('Mode RECALCULATE: Recalcul automatique de tous les soldes...');
            $this->newLine();

            foreach ($accounts as $account) {
                $oldBalance = $account->balance;
                $account->recalculateBalance();
                $account->refresh();
                $delta = $account->balance - $oldBalance;

                $status = $delta === 0
                    ? '✓ inchangé'
                    : ($delta > 0 ? '+' : '') . $fmt($delta) . ' FCFA';

                $this->line("  {$account->name}: {$fmt($oldBalance)} → {$fmt($account->balance)} ({$status})");
            }

            $this->newLine();
            $this->info('Soldes recalculés avec succès.');
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

                $account->update([
                    'initial_balance' => $newBalance,
                    'balance' => $newBalance,
                ]);

                $this->info("    → Solde mis à jour: " . number_format($newBalance, 0, ',', ' ') . " FCFA");
                $this->newLine();
            }

            $this->info('Soldes synchronisés avec succès.');
        } else {
            $this->newLine();
            $this->line('Options disponibles:');
            $this->line('  --show        : Afficher seulement (aucune modification)');
            $this->line('  --recalculate : Recalculer automatiquement depuis les transactions');
            $this->line('  --reset       : Entrer manuellement les vrais soldes');
            $this->newLine();
        }
    }
}
