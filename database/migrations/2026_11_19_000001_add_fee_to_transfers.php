<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module transferts-frais (3c) : frais d'un transfert, portés par une dépense séparée liée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('fee')->default(0)->after('amount');
            $table->foreignUuid('fee_transaction_id')->nullable()->after('fee')
                ->constrained('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_transaction_id');
            $table->dropColumn('fee');
        });
    }
};
