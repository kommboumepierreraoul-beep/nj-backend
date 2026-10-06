<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Montant total credite via des avoirs (AVOIR, CreditNoteController) sur cette commande
    // (Doc/factures_modele_donnees.md, section 9) — jamais assigne en masse (absent du
    // #[Fillable] de SalesOrder, meme convention que payment_status), incremente via
    // forceFill() a chaque emission d'avoir. Reduit le montant restant du pris en compte
    // par SalesOrderPaymentController::recalculatePaymentStatus() (effectiveTotal =
    // total_amount - credited_amount). Valeur par defaut 0 : retro-compatible avec toutes
    // les commandes existantes, aucun avoir n'ayant jamais pu etre emis avant cette
    // migration.
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('credited_amount', 14, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('credited_amount');
        });
    }
};
