<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catégories système (user_id NULL) portant les opérations d'écart de rapprochement.
 * UUID fixes : le code les reconnaît sans requête et le mobile les reçoit par la synchro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $base = ['user_id' => null, 'name' => 'Écart de rapprochement', 'icon' => 'scale', 'color' => '#8E8E93',
            'parent_id' => null, 'order_index' => 999, 'is_system' => true, 'budget_limit' => null,
            'created_at' => $now, 'updated_at' => $now];

        DB::table('categories')->insertOrIgnore([
            ['id' => Category::RECONCILIATION_EXPENSE_ID, 'type' => 'expense'] + $base,
            ['id' => Category::RECONCILIATION_INCOME_ID, 'type' => 'income'] + $base,
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('id', Category::RECONCILIATION_IDS)->delete();
    }
};
