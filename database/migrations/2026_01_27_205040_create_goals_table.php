<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['savings', 'debt', 'investment', 'custom'])->default('savings');
            $table->bigInteger('target_amount');
            $table->bigInteger('current_amount')->default(0);
            $table->date('target_date')->nullable();
            $table->uuid('account_id')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->string('color', 7)->default('#0666EB');
            $table->string('icon')->default('flag');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
