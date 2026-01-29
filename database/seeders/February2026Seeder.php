<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Account;
use App\Models\User;
use App\Models\BudgetClosure;
use App\Models\BudgetCycle;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class February2026Seeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'mebodoaristide@gmail.com')->first();

        if (!$user) {
            $this->command->error('Utilisateur non trouvé.');
            return;
        }

        // Utiliser le Compte Courant (où le salaire est reçu)
        $account = Account::where('user_id', $user->id)->where('name', 'Compte Courant')->first();

        if (!$account) {
            $this->command->error('Compte Courant non trouvé.');
            return;
        }

        // ===== MIGRATION DES TRANSACTIONS PORTEFEUILLE → COMPTE COURANT =====
        $this->migratePortefeuilleTransactions($user, $account);

        // Charger les catégories
        $categories = Category::where('is_system', true)
            ->orWhere('user_id', $user->id)
            ->pluck('id', 'name');

        // ===== CLÔTURE JANVIER 2026 =====
        $this->closeJanuary2026($user, $categories);

        // ===== OUVRIR FÉVRIER 2026 =====
        $this->openFebruary2026($user);

        // ===== SUPPRIMER LES TRANSACTIONS FÉVRIER EXISTANTES =====
        $this->cleanupFebruaryTransactions($user);

        $this->command->info('Import Février 2026...');

        // ===== REVENUS =====
        // La période budgétaire Février commence le 29 janvier (réception salaire le 27)
        $incomes = [
            ['amount' => 700000, 'category' => 'Salaire', 'description' => 'Salaire Février', 'date' => '2026-01-29'],
            ['amount' => 50000, 'category' => 'Projets/Freelance', 'description' => 'Mbira', 'beneficiary' => 'Mbira', 'date' => '2026-01-29'],
        ];

        // ===== DEPENSES =====
        // Toutes les dépenses datées au 29 janvier 2026 (début période Février)
        $expenses = [
            // Transport
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'date' => '2026-01-29'],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'date' => '2026-01-29'],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'date' => '2026-01-29'],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'date' => '2026-01-29'],

            // Alimentation (Petit déjeuner, Déjeuner)
            ['amount' => 1500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'date' => '2026-01-29'],
            ['amount' => 500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'date' => '2026-01-29'],
            ['amount' => 3000, 'category' => 'Alimentation', 'description' => 'Déjeuner', 'date' => '2026-01-29'],
            ['amount' => 500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'date' => '2026-01-29'],
            ['amount' => 2000, 'category' => 'Alimentation', 'description' => 'Déjeuner', 'date' => '2026-01-29'],

            // Sorties/Loisirs
            ['amount' => 4000, 'category' => 'Sorties/Loisirs', 'description' => 'Sortie Loisir', 'date' => '2026-01-29'],

            // Dons
            ['amount' => 5000, 'category' => 'Fonds d\'Aide/Dons', 'description' => 'Don', 'date' => '2026-01-29'],

            // Événements (Cotisation décès)
            ['amount' => 20000, 'category' => 'Événements', 'description' => 'Cotisation décès', 'date' => '2026-01-29'],

            // Santé
            ['amount' => 60000, 'category' => 'Santé', 'description' => 'Hôpital', 'date' => '2026-01-29'],

            // Divers (Dette ITC)
            ['amount' => 55000, 'category' => 'Divers', 'description' => 'Dette ITC', 'date' => '2026-01-29'],
        ];

        $now = now();

        // Insérer les revenus (sans déclencher les events Eloquent)
        foreach ($incomes as $income) {
            $categoryId = $categories[$income['category']] ?? null;

            if (!$categoryId) {
                $this->command->warn("Catégorie non trouvée: {$income['category']}");
                continue;
            }

            DB::table('transactions')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'type' => 'income',
                'amount' => $income['amount'],
                'description' => $income['description'],
                'beneficiary' => $income['beneficiary'] ?? null,
                'date' => $income['date'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Insérer les dépenses (sans déclencher les events Eloquent)
        foreach ($expenses as $expense) {
            $categoryId = $categories[$expense['category']] ?? null;

            if (!$categoryId) {
                $this->command->warn("Catégorie non trouvée: {$expense['category']}");
                continue;
            }

            DB::table('transactions')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'type' => 'expense',
                'amount' => $expense['amount'],
                'description' => $expense['description'],
                'beneficiary' => $expense['beneficiary'] ?? null,
                'date' => $expense['date'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Résumé
        $totalIncome = array_sum(array_column($incomes, 'amount'));
        $totalExpense = array_sum(array_column($expenses, 'amount'));

        $this->command->info("Revenus: " . number_format($totalIncome, 0, ',', ' ') . " FCFA");
        $this->command->info("Dépenses: " . number_format($totalExpense, 0, ',', ' ') . " FCFA");
        $this->command->info("Solde Février: " . number_format($totalIncome - $totalExpense, 0, ',', ' ') . " FCFA");
        $this->command->info('Import Février 2026 terminé (soldes non impactés).');
    }

    private function cleanupFebruaryTransactions($user): void
    {
        // Supprimer toutes les transactions de la période Février 2026 (29 jan - 28 fév)
        $deleted = DB::table('transactions')
            ->where('user_id', $user->id)
            ->where('date', '>=', '2026-01-29')
            ->where('date', '<=', '2026-02-28')
            ->delete();

        if ($deleted > 0) {
            $this->command->info("Supprimé {$deleted} transactions existantes de Février 2026.");
        }
    }

    private function migratePortefeuilleTransactions($user, $compteCourant): void
    {
        $portefeuille = Account::where('user_id', $user->id)
            ->where('name', 'Portefeuille')
            ->first();

        if (!$portefeuille) {
            $this->command->info('Compte Portefeuille non trouvé, migration ignorée.');
            return;
        }

        // Compter les transactions à migrer
        $count = DB::table('transactions')
            ->where('account_id', $portefeuille->id)
            ->count();

        if ($count === 0) {
            $this->command->info('Aucune transaction à migrer depuis Portefeuille.');
            return;
        }

        // Migrer les transactions vers Compte Courant (sans déclencher les events)
        DB::table('transactions')
            ->where('account_id', $portefeuille->id)
            ->update(['account_id' => $compteCourant->id]);

        $this->command->info("Migré {$count} transactions de Portefeuille → Compte Courant");
    }

    private function closeJanuary2026($user, $categories): void
    {
        // Supprimer l'ancienne clôture si elle existe pour recalculer
        BudgetClosure::where('user_id', $user->id)
            ->where('year', 2026)
            ->where('month', 1)
            ->delete();

        // Calculer les revenus de Janvier 2026 (transactions avant le 29 janvier)
        $januaryIncome = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->where('date', '<', '2026-01-29')
            ->whereYear('date', 2026)
            ->sum('amount');

        // Calculer les dépenses de Janvier 2026 (transactions avant le 29 janvier)
        $januaryExpenses = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->where('date', '<', '2026-01-29')
            ->whereYear('date', 2026)
            ->sum('amount');

        // Solde net = revenus - dépenses (peut être négatif)
        $netBalance = $januaryIncome - $januaryExpenses;

        // Détails par catégorie
        $expenseCategories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->where('type', 'expense')
        ->get();

        $details = [];
        foreach ($expenseCategories as $cat) {
            $spent = Transaction::where('user_id', $user->id)
                ->where('category_id', $cat->id)
                ->where('type', 'expense')
                ->where('date', '<', '2026-01-29')
                ->whereYear('date', 2026)
                ->sum('amount');

            if ($spent > 0) {
                $details[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'budget' => $cat->budget_limit ?? 0,
                    'spent' => $spent,
                ];
            }
        }

        // Créer la clôture (total_budget = revenus, total_saved = solde net)
        BudgetClosure::create([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'total_budget' => $januaryIncome,
            'total_spent' => $januaryExpenses,
            'total_saved' => $netBalance,
            'details' => $details,
        ]);

        $status = $netBalance >= 0 ? 'Excédent' : 'Déficit';
        $this->command->info("Janvier 2026 clôturé - Revenus: " . number_format($januaryIncome, 0, ',', ' ') . " | Dépenses: " . number_format($januaryExpenses, 0, ',', ' ') . " | {$status}: " . number_format($netBalance, 0, ',', ' '));

        // Clôturer le cycle actif de Janvier si existant
        $activeCycle = BudgetCycle::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($activeCycle) {
            $activeCycle->update([
                'end_date' => '2026-01-28 23:59:59',
                'status' => 'closed',
                'total_spent' => $januaryExpenses,
                'total_budget' => $totalBudget,
            ]);
            $this->command->info('Cycle Janvier 2026 clôturé.');
        }

        $this->command->info("Janvier 2026 clôturé - Budget: " . number_format($totalBudget, 0, ',', ' ') . " | Dépensé: " . number_format($januaryExpenses, 0, ',', ' ') . " | Économisé: " . number_format($totalSaved, 0, ',', ' '));
    }

    private function openFebruary2026($user): void
    {
        // Budget total depuis les catégories
        $totalBudget = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_system', true);
        })
        ->where('type', 'expense')
        ->whereNotNull('budget_limit')
        ->sum('budget_limit');

        // Créer ou mettre à jour le cycle Février 2026
        $cycle = BudgetCycle::updateOrCreate(
            [
                'user_id' => $user->id,
                'period_name' => 'Février 2026',
            ],
            [
                'start_date' => '2026-01-29 00:00:00',
                'end_date' => null,
                'total_budget' => $totalBudget,
                'total_spent' => 0,
                'status' => 'active',
            ]
        );

        $this->command->info("Cycle Février 2026 (début: 29/01/2026) - Budget: " . number_format($totalBudget, 0, ',', ' ') . " FCFA");
    }
}
