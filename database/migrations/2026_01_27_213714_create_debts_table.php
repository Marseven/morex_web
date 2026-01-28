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
        Schema::create('debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Nom de la dette/créance ou personne
            $table->enum('type', ['debt', 'credit']); // debt = je dois, credit = on me doit
            $table->integer('initial_amount'); // Montant initial
            $table->integer('current_amount'); // Montant restant
            $table->date('due_date')->nullable(); // Date d'échéance
            $table->text('description')->nullable();
            $table->string('contact_name')->nullable(); // Nom du créancier/débiteur
            $table->string('contact_phone')->nullable();
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->string('color')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
