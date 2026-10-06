<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Journal d'audit (entite "AuditLog" du modele UML, jusqu'ici non implementee) :
    // trace immuable des actions sensibles de gestion des utilisateurs (creation,
    // modification, changement de statut, suppression, changement de permissions...).
    // Voir Doc/spec_pages_utilisateurs.md, section 6 (page E1) et section 9.
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // L'acteur peut devenir null si son compte est supprime definitivement :
            // le journal reste consultable meme apres suppression de l'acteur.
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_value_json')->nullable();
            $table->json('new_value_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
