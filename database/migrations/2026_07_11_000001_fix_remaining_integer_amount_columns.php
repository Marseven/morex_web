<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige les dernières colonnes de montants (FCFA) restées en `integer`.
 * En integer, tout cumul > ~2,147 milliards FCFA déborde silencieusement.
 * - balance_history.amount / balance_before / balance_after (table créée après le premier fix)
 * - budget_closures.total_income (resté integer alors que ses colonnes sœurs sont bigInteger)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_history', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('balance_before')->change();
            $table->bigInteger('balance_after')->change();
        });

        Schema::table('budget_closures', function (Blueprint $table) {
            $table->bigInteger('total_income')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('balance_history', function (Blueprint $table) {
            $table->integer('amount')->change();
            $table->integer('balance_before')->change();
            $table->integer('balance_after')->change();
        });

        Schema::table('budget_closures', function (Blueprint $table) {
            $table->integer('total_income')->default(0)->change();
        });
    }
};
