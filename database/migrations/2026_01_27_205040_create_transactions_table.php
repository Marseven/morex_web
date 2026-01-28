<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->enum('type', ['expense', 'income', 'transfer'])->default('expense');
            $table->uuid('category_id')->nullable();
            $table->uuid('account_id');
            $table->string('beneficiary')->nullable();
            $table->text('description')->nullable();
            $table->date('date');
            $table->uuid('transfer_to_account_id')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('transfer_to_account_id')->references('id')->on('accounts')->nullOnDelete();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'type']);
            $table->index(['account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
