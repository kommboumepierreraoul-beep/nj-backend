<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Remboursements (Doc/factures_modele_donnees.md, section 9) : sales_order_payments ne
    // portait jusqu'ici que des encaissements. 'direction' distingue desormais ENCAISSEMENT
    // (comportement inchange, valeur par defaut — retro-compatible avec toutes les lignes
    // existantes) de REMBOURSEMENT (montant restitue au client, deduit du paye dans
    // SalesOrderPaymentController::recalculatePaymentStatus()). 'invoice_id' rattache
    // optionnellement un mouvement a un document Invoice (ex : un remboursement consecutif
    // a un avoir) — nullable, un encaissement ordinaire n'en a pas. La relation
    // Invoice::refundPayments() (deja presente sur le modele depuis une session anterieure)
    // attendait cette colonne pour fonctionner.
    public function up(): void
    {
        Schema::table('sales_order_payments', function (Blueprint $table) {
            $table->string('direction')->default('ENCAISSEMENT')->after('sales_order_id');
            $table->foreignId('invoice_id')->nullable()->after('direction')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('direction');
        });
    }
};
