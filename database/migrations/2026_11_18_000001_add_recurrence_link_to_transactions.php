<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module recurrences-serveur (6) : une opération générée porte sa récurrence et son échéance,
 * uniques ensemble (jamais deux fois la même échéance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('recurring_transaction_id')->nullable()->after('transfer_to_account_id')
                ->constrained('recurring_transactions')->nullOnDelete();
            $table->date('occurrence_date')->nullable()->after('recurring_transaction_id');
            $table->unique(['recurring_transaction_id', 'occurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['recurring_transaction_id', 'occurrence_date']);
            $table->dropConstrainedForeignId('recurring_transaction_id');
            $table->dropColumn('occurrence_date');
        });
    }
};
