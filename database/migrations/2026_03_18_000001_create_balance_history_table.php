<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts');
            $table->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out', 'adjustment', 'creation']);
            $table->integer('amount');              // toujours positif
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type', 20)->nullable();  // 'transaction', 'transfer', 'manual'
            $table->uuid('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_history');
    }
};
