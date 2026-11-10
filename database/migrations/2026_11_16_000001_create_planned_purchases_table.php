<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module achats-prevus (5d) : liste d'achats prévus, lien vers l'opération réalisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('estimated_amount');
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('planned_for');
            $table->string('status', 16)->default('planned');
            $table->foreignUuid('transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('unplanned')->default(false);
            $table->timestamp('bought_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'planned_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_purchases');
    }
};
