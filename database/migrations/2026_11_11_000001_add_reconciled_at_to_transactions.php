<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Pointage : l'opération figure sur un relevé déjà rapproché
            $table->timestamp('reconciled_at')->nullable()->after('status');
            $table->index(['account_id', 'reconciled_at'], 'transactions_account_reconciled_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_account_reconciled_index');
            $table->dropColumn('reconciled_at');
        });
    }
};
