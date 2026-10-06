<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Textes libres saisis a l'emission d'une proforma comparative (Doc/proforma_
    // comparatif_addendum.md, decision n°5) : points forts/points d'attention/
    // recommandation par option (cle = VariantLevel), plus un bloc "notes" (conditions
    // commerciales, delai de production, paiement, douane/livraison). Un seul champ JSON,
    // meme pattern que invoices.currency_equivalents (Schema::table, la table existe deja
    // depuis 2026_08_17_000003).
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('proposal_details')->nullable()->after('currency_equivalents');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('proposal_details');
        });
    }
};
