<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Liaison RFQ -> commande fournisseur (Doc/analyse_flux_modele_donnees.md, decision
    // ouverte §0bis.1 / §8.4 : "aucune liaison en base entre une commande fournisseur et la
    // RFQ dont elle est issue"). Deux colonnes nullable, renseignees a la creation d'un PO
    // issu d'un devis retenu (Supplier\PurchaseOrderController::store()) :
    //  - rfq_id : rend calculable le vrai taux de transformation RFQ -> commande
    //    (FlowAnalyticsController::buildPurchaseFlow()), en plus du proxy "taux de selection
    //    de devis" jusque-la seul disponible ;
    //  - rfq_supplier_quote_id : garde la trace du devis precis a l'origine du PO (utile pour
    //    l'estimation de marge et l'audit).
    // nullOnDelete : la suppression d'une RFQ/d'un devis ne doit pas emporter la commande
    // fournisseur, seulement rompre le lien.
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('rfq_id')->nullable()->after('sales_order_id')->constrained('rfqs')->nullOnDelete();
            $table->foreignId('rfq_supplier_quote_id')->nullable()->after('rfq_id')->constrained('rfq_supplier_quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['rfq_id']);
            $table->dropForeign(['rfq_supplier_quote_id']);
            $table->dropColumn(['rfq_id', 'rfq_supplier_quote_id']);
        });
    }
};
