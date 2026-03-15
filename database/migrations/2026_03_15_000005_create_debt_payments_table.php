<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('debt_id');
            $table->uuid('account_id');
            $table->uuid('transaction_id')->nullable();
            $table->bigInteger('amount');
            $table->date('date');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('debt_id')->references('id')->on('debts');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('transaction_id')->references('id')->on('transactions');

            $table->index(['user_id', 'debt_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
