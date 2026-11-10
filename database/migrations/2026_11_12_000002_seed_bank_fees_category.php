<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Catégorie système des frais bancaires et mobile money (module releves-sms, RM-2). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->insertOrIgnore([
            'id' => Category::BANK_FEES_ID, 'user_id' => null, 'name' => 'Frais bancaires', 'type' => 'expense',
            'icon' => 'receipt', 'color' => '#8E8E93', 'parent_id' => null, 'order_index' => 998,
            'is_system' => true, 'budget_limit' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('id', Category::BANK_FEES_ID)->delete();
    }
};
