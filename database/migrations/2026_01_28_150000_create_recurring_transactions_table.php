<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('account_id');
            $table->uuid('category_id')->nullable();
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->bigInteger('amount');
            $table->string('beneficiary')->nullable();
            $table->text('description')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->tinyInteger('day_of_month')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_generated_date')->nullable();
            $table->date('next_due_date');
            $table->integer('remaining_occurrences')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
