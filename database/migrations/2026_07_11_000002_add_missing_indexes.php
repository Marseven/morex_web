<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index manquants sur des colonnes de filtrage fréquent.
 * - transactions.status : filtre des transactions en attente de validation (SMS)
 * - transactions (user_id, category_id, date) : clôtures budgétaires (SUM par catégorie/période)
 * - debts (user_id, status) et (user_id, type) : listes de dettes/créances
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('status', 'transactions_status_index');
            $table->index(['user_id', 'category_id', 'date'], 'transactions_user_category_date_index');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'debts_user_status_index');
            $table->index(['user_id', 'type'], 'debts_user_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_status_index');
            $table->dropIndex('transactions_user_category_date_index');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex('debts_user_status_index');
            $table->dropIndex('debts_user_type_index');
        });
    }
};
