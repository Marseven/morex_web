<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL nécessite du SQL direct pour modifier un enum ; SQLite (tests) matérialise
        // l'enum en contrainte CHECK et doit être modifié via un rebuild de colonne.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('current', 'checking', 'savings', 'cash', 'credit', 'investment') DEFAULT 'current'");
        } else {
            Schema::table('accounts', function (Blueprint $table) {
                $table->enum('type', ['current', 'checking', 'savings', 'cash', 'credit', 'investment'])->default('current')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('current', 'savings', 'investment') DEFAULT 'current'");
        } else {
            Schema::table('accounts', function (Blueprint $table) {
                $table->enum('type', ['current', 'savings', 'investment'])->default('current')->change();
            });
        }
    }
};
