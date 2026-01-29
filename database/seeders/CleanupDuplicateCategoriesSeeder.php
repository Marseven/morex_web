<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Nettoyage des catégories en double...');

        // Récupérer les catégories groupées par nom et type
        $duplicates = DB::table('categories')
            ->select('name', 'type', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('name', 'type')
            ->having('count', '>', 1)
            ->get();

        $this->command->info("Trouvé {$duplicates->count()} groupes de doublons");

        foreach ($duplicates as $dup) {
            // ID à garder (le premier créé)
            $keepId = $dup->keep_id;

            // IDs à supprimer
            $deleteIds = Category::where('name', $dup->name)
                ->where('type', $dup->type)
                ->where('id', '!=', $keepId)
                ->pluck('id');

            $this->command->info("- {$dup->name} ({$dup->type}): garder {$keepId}, supprimer " . $deleteIds->count() . " doublons");

            // Mettre à jour les transactions pour pointer vers la catégorie conservée
            Transaction::whereIn('category_id', $deleteIds)
                ->update(['category_id' => $keepId]);

            // Supprimer les doublons
            Category::whereIn('id', $deleteIds)->delete();
        }

        // Supprimer les catégories obsolètes qui ne sont pas dans le CategorySeeder
        $validCategories = [
            // Dépenses
            'Épargne/Investissement', 'Logement', 'Dîmes', 'Abonnements',
            'Emilie/Courses', 'Alimentation', 'Transport', 'Sorties/Loisirs',
            'Fonds d\'Aide/Dons', 'Santé', 'Divers', 'Événements',
            'Équipement', 'Factures', 'Restaurants', 'Shopping',
            // Revenus
            'Salaire', 'Projets/Freelance', 'Remboursements', 'Autres revenus',
        ];

        // Catégories à supprimer (obsolètes)
        $obsoleteCategories = [
            'Freelance', 'Loisirs', 'Dons', 'Sorties', 'Éducation',
            'Investissements', 'Cadeaux', 'Autres dépenses',
        ];

        foreach ($obsoleteCategories as $name) {
            $obsolete = Category::where('name', $name)->first();
            if ($obsolete) {
                // Trouver une catégorie de remplacement
                $replacement = $this->findReplacement($name);
                if ($replacement) {
                    $replacementCat = Category::where('name', $replacement)
                        ->where('type', $obsolete->type)
                        ->first();

                    if ($replacementCat) {
                        // Migrer les transactions
                        $count = Transaction::where('category_id', $obsolete->id)->count();
                        Transaction::where('category_id', $obsolete->id)
                            ->update(['category_id' => $replacementCat->id]);
                        $this->command->info("- Migré {$count} transactions de '{$name}' vers '{$replacement}'");
                    }
                }
                $obsolete->delete();
                $this->command->info("- Supprimé catégorie obsolète: {$name}");
            }
        }

        $remaining = Category::count();
        $this->command->info("Nettoyage terminé. {$remaining} catégories restantes.");
    }

    private function findReplacement(string $name): ?string
    {
        $mapping = [
            'Freelance' => 'Projets/Freelance',
            'Loisirs' => 'Sorties/Loisirs',
            'Dons' => 'Fonds d\'Aide/Dons',
            'Sorties' => 'Sorties/Loisirs',
            'Éducation' => 'Divers',
            'Investissements' => 'Autres revenus',
            'Cadeaux' => 'Autres revenus',
            'Autres dépenses' => 'Divers',
        ];

        return $mapping[$name] ?? null;
    }
}
