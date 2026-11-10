<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empreinte du SMS source : un même SMS reçu deux fois ne crée qu'une transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->char('sms_fingerprint', 64)->nullable()->after('status');
            $table->unique(['user_id', 'sms_fingerprint'], 'transactions_user_sms_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_user_sms_fingerprint_unique');
            $table->dropColumn('sms_fingerprint');
        });
    }
};
