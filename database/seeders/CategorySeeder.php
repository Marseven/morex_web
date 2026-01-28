<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategories = [
            ['name' => 'Alimentation', 'icon' => 'shopping-cart', 'color' => '#FF6B6B'],
            ['name' => 'Transport', 'icon' => 'car', 'color' => '#4ECDC4'],
            ['name' => 'Logement', 'icon' => 'home', 'color' => '#45B7D1'],
            ['name' => 'Factures', 'icon' => 'file-text', 'color' => '#96CEB4'],
            ['name' => 'Santé', 'icon' => 'heart', 'color' => '#FF8C94'],
            ['name' => 'Loisirs', 'icon' => 'smile', 'color' => '#DDA0DD'],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'color' => '#F7DC6F'],
            ['name' => 'Restaurants', 'icon' => 'coffee', 'color' => '#E59866'],
            ['name' => 'Éducation', 'icon' => 'book', 'color' => '#85C1E9'],
            ['name' => 'Dons', 'icon' => 'gift', 'color' => '#C39BD3'],
            ['name' => 'Sorties', 'icon' => 'users', 'color' => '#F1948A'],
            ['name' => 'Abonnements', 'icon' => 'repeat', 'color' => '#7DCEA0'],
            ['name' => 'Autres dépenses', 'icon' => 'more-horizontal', 'color' => '#AEB6BF'],
        ];

        $incomeCategories = [
            ['name' => 'Salaire', 'icon' => 'briefcase', 'color' => '#27AE60'],
            ['name' => 'Freelance', 'icon' => 'laptop', 'color' => '#3498DB'],
            ['name' => 'Investissements', 'icon' => 'trending-up', 'color' => '#9B59B6'],
            ['name' => 'Cadeaux', 'icon' => 'gift', 'color' => '#E74C3C'],
            ['name' => 'Remboursements', 'icon' => 'refresh-cw', 'color' => '#1ABC9C'],
            ['name' => 'Autres revenus', 'icon' => 'plus-circle', 'color' => '#95A5A6'],
        ];

        $orderIndex = 0;
        foreach ($expenseCategories as $category) {
            Category::create([
                'id' => Str::uuid(),
                'user_id' => null,
                'name' => $category['name'],
                'type' => 'expense',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'order_index' => $orderIndex++,
                'is_system' => true,
            ]);
        }

        $orderIndex = 0;
        foreach ($incomeCategories as $category) {
            Category::create([
                'id' => Str::uuid(),
                'user_id' => null,
                'name' => $category['name'],
                'type' => 'income',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'order_index' => $orderIndex++,
                'is_system' => true,
            ]);
        }
    }
}
