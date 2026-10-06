<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Historique du flux achat (Doc/analyse_flux_modele_donnees.md, decision §0bis.1,
    // option (a) retenue par l'utilisateur le 2026-08-26) : miroir exact de
    // sales_order_status_history, qui n'avait jusqu'ici aucun equivalent cote achat
    // (purchase_orders n'a que order_date/expected_delivery_date/actual_delivery_date,
    // pas de detail par etape DRAFT->SENT->CONFIRMED->IN_PRODUCTION->SHIPPED->RECEIVED).
    // Journal immuable (pas d'updated_at), meme pattern que sales_order_status_history /
    // product_price_history / system_traces. Cable dans
    // Supplier\PurchaseOrderController::store()/update().
    public function up(): void
    {
        Schema::create('purchase_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_status_history');
    }
};
