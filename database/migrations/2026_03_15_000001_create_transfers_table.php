<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('from_account_id');
            $table->uuid('to_account_id');
            $table->bigInteger('amount');
            $table->text('description')->nullable();
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('to_account_id')->references('id')->on('accounts')->cascadeOnDelete();

            $table->index(['user_id', 'date']);
            $table->index('from_account_id');
            $table->index('to_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
