<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modifier l'enum pour ajouter emergency_fund
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE goals MODIFY COLUMN type ENUM('savings', 'debt', 'investment', 'custom', 'emergency_fund') DEFAULT 'savings'");
        } else {
            Schema::table('goals', function (Blueprint $table) {
                $table->enum('type', ['savings', 'debt', 'investment', 'custom', 'emergency_fund'])->default('savings')->change();
            });
        }

        // Mettre à jour les objectifs "Fonds d'Urgence" avec le bon type
        DB::table('goals')
            ->where('name', 'like', '%Urgence%')
            ->orWhere('name', 'like', '%Emergency%')
            ->update(['type' => 'emergency_fund']);
    }

    public function down(): void
    {
        // Remettre les emergency_fund en savings
        DB::table('goals')
            ->where('type', 'emergency_fund')
            ->update(['type' => 'savings']);

        // Retirer emergency_fund de l'enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE goals MODIFY COLUMN type ENUM('savings', 'debt', 'investment', 'custom') DEFAULT 'savings'");
        } else {
            Schema::table('goals', function (Blueprint $table) {
                $table->enum('type', ['savings', 'debt', 'investment', 'custom'])->default('savings')->change();
            });
        }
    }
};
