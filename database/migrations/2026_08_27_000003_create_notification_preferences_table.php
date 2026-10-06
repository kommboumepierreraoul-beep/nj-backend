<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Préférences de canal par utilisateur et par catégorie (Doc/notifications_modele_donnees.md,
// §3.3, décision §2.4). Pas de ligne = valeurs par défaut de NotificationCategory::defaultChannels()
// (voir NotificationPreference::resolveFor()). Pas de colonne in_app_enabled : le canal in-app
// n'est jamais désactivable (décision §2.3). Un seul canal désactivable (email) — SMS/WhatsApp
// retirés du périmètre le 2026-08-27.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->boolean('email_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
