<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['current', 'savings', 'investment'])->default('current');
            $table->bigInteger('initial_balance')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->string('color', 7)->default('#0666EB');
            $table->string('icon')->default('wallet');
            $table->boolean('is_default')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
