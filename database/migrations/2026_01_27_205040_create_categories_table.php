<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->string('icon')->default('folder');
            $table->string('color', 7)->default('#0666EB');
            $table->uuid('parent_id')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_system')->default(false);
            $table->bigInteger('budget_limit')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
