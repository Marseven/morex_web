<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes de relevé (Airtel, BGFI) rapprochées des opérations saisies (module releves-sms).
 * Le texte brut du relevé n'est jamais conservé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('amount');            // signé : négatif = sortie du compte
            $table->bigInteger('fee')->default(0);   // frais indiqués par le relevé, positifs
            $table->string('label', 255)->nullable();
            $table->string('external_ref', 64)->nullable();
            $table->string('source', 10);            // sms | paste | csv
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUuid('fee_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending | matched | created | ignored
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'external_ref']);
            // Une opération (ou un frais) n'est liée qu'à une seule ligne de relevé (RM-3), même en concurrence
            $table->unique('transaction_id');
            $table->unique('fee_transaction_id');
            $table->index(['account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_lines');
    }
};
