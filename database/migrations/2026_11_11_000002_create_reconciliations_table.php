<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('statement_balance');
            $table->bigInteger('balance_before');
            $table->bigInteger('difference');
            $table->foreignUuid('adjustment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('reconciled_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'reconciled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
