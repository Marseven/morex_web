<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanDuplicateCategoriesSeeder extends Seeder
{
    /**
     * Nettoie les catégories utilisateur qui sont des doublons de catégories système
     */
    public function run(): void
    {
        $this->command->info('Nettoyage des catégories dupliquées...');

        // Récupérer toutes les catégories système
        $systemCategories = Category::whereNull('user_id')->get();

        foreach ($systemCategories as $systemCat) {
            // Trouver les doublons utilisateur avec le même nom et type
            $userDuplicates = Category::whereNotNull('user_id')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($systemCat->name))])
                ->where('type', $systemCat->type)
                ->get();

            foreach ($userDuplicates as $duplicate) {
                $this->command->info("  Suppression doublon: {$duplicate->name} (user_id: {$duplicate->user_id})");

                // Migrer les transactions vers la catégorie système
                DB::table('transactions')
                    ->where('category_id', $duplicate->id)
                    ->update(['category_id' => $systemCat->id]);

                // Migrer les transactions récurrentes
                DB::table('recurring_transactions')
                    ->where('category_id', $duplicate->id)
                    ->update(['category_id' => $systemCat->id]);

                // Supprimer le doublon
                $duplicate->forceDelete();
            }
        }

        $this->command->info('Nettoyage terminé!');
    }
}
