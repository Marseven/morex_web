<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Paramètres de cycle budgétaire par utilisateur
        Schema::create('budget_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->integer('preferred_start_day')->default(1); // Jour préféré de début (1-31)
            $table->integer('tolerance_start_day')->default(25); // Début fenêtre salaire
            $table->integer('tolerance_end_day')->default(31); // Fin fenêtre salaire
            $table->foreignUuid('salary_category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignUuid('salary_account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->boolean('auto_detect_salary')->default(true);
            $table->timestamps();
        });

        // Cycles budgétaires individuels
        Schema::create('budget_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null si cycle actif
            $table->string('period_name'); // "Février 2026"
            $table->integer('total_budget')->default(0);
            $table->integer('total_spent')->default(0);
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->foreignUuid('trigger_transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_cycles');
        Schema::dropIfExists('budget_settings');
    }
};
