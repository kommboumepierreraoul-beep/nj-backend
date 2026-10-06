<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Volet "trace systeme" (Doc/audit_trace_systeme.md, section 3.4) :
    // evenements techniques et de securite (connexions, deconnexions, jetons
    // invalides/expires, comptes desactives, permissions/roles refuses...).
    // Table separee du journal d'audit metier (audit_logs) : le volume est
    // potentiellement plus eleve (chaque tentative, y compris echouee) et
    // l'entite metier n'est pas toujours pertinente (ex. un echec de
    // connexion n'a pas d'utilisateur resolu).
    public function up(): void
    {
        Schema::create('system_traces', function (Blueprint $table) {
            $table->id();
            // Nullable : un echec de connexion ou un jeton invalide n'a pas
            // toujours d'utilisateur resolu.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('email_attempted')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('route')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_traces');
    }
};
