<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Objectif suivi sur un compte (module suivi-objectifs 5a). `account_id` existant = compte suivi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->date('tracked_since')->nullable()->after('account_id');
            $table->bigInteger('tracked_start_amount')->nullable()->after('tracked_since');
            $table->bigInteger('tracked_account_balance')->nullable()->after('tracked_start_amount');
            $table->bigInteger('monthly_target')->nullable()->after('tracked_account_balance');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['tracked_since', 'tracked_start_amount', 'tracked_account_balance', 'monthly_target']);
        });
    }
};
