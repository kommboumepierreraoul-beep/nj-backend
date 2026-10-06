<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Historique metier des statuts (Doc/commandes_modele_donnees.md, section 5) :
    // journal immuable (pas d'updated_at), meme pattern que product_price_history /
    // exchange_rate_history / system_traces. Complete, sans le remplacer, le journal
    // d'audit generique audit_logs (decision ouverte n°6 du document, retenue : les
    // deux couches sont cablees des le lancement de ce module).
    public function up(): void
    {
        Schema::create('sales_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_status_history');
    }
};
