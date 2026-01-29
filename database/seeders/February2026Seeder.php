<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class February2026Seeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'mebodoaristide@gmail.com')->first();

        if (!$user) {
            $this->command->error('Utilisateur non trouvé.');
            return;
        }

        $account = Account::where('user_id', $user->id)->where('is_default', true)->first();

        if (!$account) {
            $this->command->error('Compte principal non trouvé.');
            return;
        }

        // Charger les catégories
        $categories = Category::where('is_system', true)
            ->orWhere('user_id', $user->id)
            ->pluck('id', 'name');

        $this->command->info('Import Février 2026...');

        // ===== REVENUS =====
        $incomes = [
            ['amount' => 700000, 'category' => 'Salaire', 'description' => 'Salaire Février', 'day' => 1],
            ['amount' => 50000, 'category' => 'Projets/Freelance', 'description' => 'Mbira', 'beneficiary' => 'Mbira', 'day' => 5],
        ];

        // ===== DEPENSES =====
        $expenses = [
            // Transport
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'day' => 3],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'day' => 5],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'day' => 10],
            ['amount' => 500, 'category' => 'Transport', 'description' => 'Transport', 'day' => 12],

            // Alimentation (Petit déjeuner, Déjeuner)
            ['amount' => 1500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'day' => 3],
            ['amount' => 500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'day' => 6],
            ['amount' => 3000, 'category' => 'Alimentation', 'description' => 'Déjeuner', 'day' => 7],
            ['amount' => 500, 'category' => 'Alimentation', 'description' => 'Petit Déjeuner', 'day' => 9],
            ['amount' => 2000, 'category' => 'Alimentation', 'description' => 'Déjeuner', 'day' => 14],

            // Sorties/Loisirs
            ['amount' => 4000, 'category' => 'Sorties/Loisirs', 'description' => 'Sortie Loisir', 'day' => 11],

            // Dons
            ['amount' => 5000, 'category' => 'Fonds d\'Aide/Dons', 'description' => 'Don', 'day' => 4],

            // Événements (Cotisation décès)
            ['amount' => 20000, 'category' => 'Événements', 'description' => 'Cotisation décès', 'day' => 8],

            // Santé
            ['amount' => 60000, 'category' => 'Santé', 'description' => 'Hôpital', 'day' => 15],

            // Divers (Dette ITC)
            ['amount' => 55000, 'category' => 'Divers', 'description' => 'Dette ITC', 'day' => 10],
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
                'date' => "2026-02-{$income['day']}",
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
                'date' => "2026-02-{$expense['day']}",
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
}
