<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Arguments de proforma comparative desormais stockes en base, plutot que re-saisis a
    // chaque emission (Doc/proforma_comparatif_addendum.md — demande utilisateur du
    // 2026-09-03 : "simplifier les taches de l'equipe").
    //
    //  - product_variants : points forts / points d'attention / recommandation propres a
    //    CHAQUE variante (niveau Premier/Deuxieme/Troisieme choix). Renseignes une fois sur
    //    la fiche variante, repris automatiquement a l'emission, surchargeables par
    //    l'emetteur pour une proforma donnee.
    //  - company_settings : les 4 champs "Notes / conditions" (conditions commerciales,
    //    delai de production, paiement, douane/livraison) — memes valeurs pour toutes les
    //    proformas, donc valeurs par defaut au niveau societe (editables via
    //    Parametres -> Entreprise), surchargeables par l'emetteur.
    //
    // La proforma emise fige la valeur resolue dans invoices.proposal_details (deja
    // existant, JSON) : une modification ulterieure d'une variante ou des parametres ne
    // change jamais un document deja emis.
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('proforma_strengths')->nullable()->after('description');
            $table->json('proforma_weaknesses')->nullable()->after('proforma_strengths');
            $table->text('proforma_recommendation')->nullable()->after('proforma_weaknesses');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('default_proforma_conditions')->nullable()->after('default_proforma_validity_days');
            $table->text('default_proforma_production_delay')->nullable()->after('default_proforma_conditions');
            $table->text('default_proforma_payment_terms')->nullable()->after('default_proforma_production_delay');
            $table->text('default_proforma_customs')->nullable()->after('default_proforma_payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['proforma_strengths', 'proforma_weaknesses', 'proforma_recommendation']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'default_proforma_conditions',
                'default_proforma_production_delay',
                'default_proforma_payment_terms',
                'default_proforma_customs',
            ]);
        });
    }
};
