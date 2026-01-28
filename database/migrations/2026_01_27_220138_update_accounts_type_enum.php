<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires direct SQL to modify enum
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('current', 'checking', 'savings', 'cash', 'credit', 'investment') DEFAULT 'current'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('current', 'savings', 'investment') DEFAULT 'current'");
    }
};
