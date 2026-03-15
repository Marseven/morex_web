<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-delete all transactions of type 'transfer'
        // We keep the schema (transfer_to_account_id column) but stop using it
        DB::table('transactions')
            ->where('type', 'transfer')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        // Restore soft-deleted transfer transactions
        DB::table('transactions')
            ->where('type', 'transfer')
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);
    }
};
