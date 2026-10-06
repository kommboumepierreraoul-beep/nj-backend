<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Table du module Notifications (Doc/notifications_modele_donnees.md, §3.2, décision §2.2) :
// colonnes structurées propres au projet (comme audit_logs/system_traces) plutôt que la table
// générique par défaut de Laravel (morphs + data JSON opaque) — un seul type de destinataire
// (User) rend le polymorphisme inutile ici.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notifiable_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('category');
            $table->string('priority');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->json('channels_sent')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_user_id', 'read_at']);
            $table->index(['related_entity_type', 'related_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
