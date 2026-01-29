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
        Schema::create('budget_closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('month');
            $table->integer('total_budget')->default(0);
            $table->integer('total_spent')->default(0);
            $table->integer('total_saved')->default(0);
            $table->foreignUuid('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->json('details')->nullable(); // Détails par catégorie
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_closures');
    }
};
