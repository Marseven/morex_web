<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Alertes de plafond envoyées (module plafonds-alertes) : une par catégorie, période et seuil. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->date('cycle_start');
            $table->unsignedTinyInteger('threshold'); // 80 | 100
            $table->bigInteger('spent');
            $table->bigInteger('limit');
            $table->timestamp('created_at');

            // user_id inclus : une catégorie système (Frais bancaires) est partagée entre utilisateurs
            $table->unique(['user_id', 'category_id', 'cycle_start', 'threshold']);
            $table->index(['user_id', 'cycle_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};
