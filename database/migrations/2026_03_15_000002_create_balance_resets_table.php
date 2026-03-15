<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_resets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->uuid('account_id');
            $table->bigInteger('old_balance')->nullable();
            $table->bigInteger('new_balance')->nullable();
            $table->bigInteger('old_initial_balance')->nullable();
            $table->timestamp('reset_date')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->index(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_resets');
    }
};
