<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Catégories alignées avec l'app mobile Morex
        $expenseCategories = [
            ['name' => 'Épargne/Investissement', 'icon' => 'piggy-bank', 'color' => '#10B981'],
            ['name' => 'Logement', 'icon' => 'home', 'color' => '#F59E0B'],
            ['name' => 'Dîmes', 'icon' => 'heart', 'color' => '#8B5CF6'],
            ['name' => 'Abonnements', 'icon' => 'repeat', 'color' => '#EC4899'],
            ['name' => 'Alimentation', 'icon' => 'shopping-cart', 'color' => '#F97316', 'budget_limit' => 150000],
            ['name' => 'Transport', 'icon' => 'car', 'color' => '#6366F1', 'budget_limit' => 75000],
            ['name' => 'Sorties/Loisirs', 'icon' => 'smile', 'color' => '#EF4444', 'budget_limit' => 50000],
            ['name' => 'Fonds d\'Aide/Dons', 'icon' => 'gift', 'color' => '#EC4899', 'budget_limit' => 30000],
            ['name' => 'Santé', 'icon' => 'heart', 'color' => '#14B8A6'],
            ['name' => 'Divers', 'icon' => 'more-horizontal', 'color' => '#6B7280'],
            ['name' => 'Événements', 'icon' => 'calendar', 'color' => '#A855F7'],
            ['name' => 'Équipement', 'icon' => 'monitor', 'color' => '#0EA5E9'],
        ];

        $incomeCategories = [
            ['name' => 'Salaire', 'icon' => 'briefcase', 'color' => '#10B981'],
            ['name' => 'Projets/Freelance', 'icon' => 'laptop', 'color' => '#3B82F6'],
            ['name' => 'Remboursements', 'icon' => 'refresh-cw', 'color' => '#8B5CF6'],
            ['name' => 'Autres revenus', 'icon' => 'plus-circle', 'color' => '#6B7280'],
        ];

        $orderIndex = 0;
        foreach ($expenseCategories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'type' => 'expense', 'is_system' => true],
                [
                    'id' => Str::uuid(),
                    'user_id' => null,
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'budget_limit' => $category['budget_limit'] ?? null,
                    'order_index' => $orderIndex++,
                ]
            );
        }

        $orderIndex = 0;
        foreach ($incomeCategories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'type' => 'income', 'is_system' => true],
                [
                    'id' => Str::uuid(),
                    'user_id' => null,
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'order_index' => $orderIndex++,
                ]
            );
        }
    }
}
