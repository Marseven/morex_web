<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->bigInteger('initial_amount')->change();
            $table->bigInteger('current_amount')->change();
        });

        Schema::table('budget_cycles', function (Blueprint $table) {
            $table->bigInteger('total_budget')->default(0)->change();
            $table->bigInteger('total_spent')->default(0)->change();
        });

        Schema::table('budget_closures', function (Blueprint $table) {
            $table->bigInteger('total_budget')->default(0)->change();
            $table->bigInteger('total_spent')->default(0)->change();
            $table->bigInteger('total_saved')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->integer('initial_amount')->change();
            $table->integer('current_amount')->change();
        });

        Schema::table('budget_cycles', function (Blueprint $table) {
            $table->integer('total_budget')->default(0)->change();
            $table->integer('total_spent')->default(0)->change();
        });

        Schema::table('budget_closures', function (Blueprint $table) {
            $table->integer('total_budget')->default(0)->change();
            $table->integer('total_spent')->default(0)->change();
            $table->integer('total_saved')->default(0)->change();
        });
    }
};
