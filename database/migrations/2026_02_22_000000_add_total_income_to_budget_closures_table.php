<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_closures', function (Blueprint $table) {
            $table->integer('total_income')->default(0)->after('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_closures', function (Blueprint $table) {
            $table->dropColumn('total_income');
        });
    }
};
