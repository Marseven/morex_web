<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements Web Push (PWA) : un enregistrement par navigateur/appareil abonné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('public_key');   // clé p256dh du navigateur
            $table->string('auth_token');   // secret d'authentification
            $table->timestamps();
            // dédup gérée applicativement par endpoint (un TEXT ne peut pas être indexé unique sur MySQL)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
