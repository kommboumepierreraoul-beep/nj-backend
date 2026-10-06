<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // TVA de bout en bout (Doc/tva_addendum.md ; roadmap Phase 1, semaine 8 « bilingue +
    // export + TVA »). Le cahier des charges v2 ne detaille pas la TVA — decision de
    // conception documentee : un taux unique au niveau de la commande, applique a la base
    // « sous-total - remise + commission », qui alimente sales_orders.tax_amount et
    // s'ajoute a total_amount. `invoices.tax_rate` / `invoices.tax_amount` existaient deja
    // (migration 2026_08_17_000003) mais n'etaient jamais alimentes : ils recoivent
    // desormais une copie figee du taux/montant de la commande a l'emission.
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            // null = pas de TVA (comportement historique inchange pour les commandes
            // existantes). En pourcentage, 0 a 100.
            $table->decimal('tax_rate', 5, 2)->nullable()->after('commission_amount');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_rate');
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            // Taux propose par defaut a la creation d'une commande (editable dans
            // Parametres -> Entreprise). 0 = aucune TVA par defaut.
            $table->decimal('default_tax_rate', 5, 2)->default(0)->after('default_proforma_validity_days');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn('default_tax_rate');
        });
    }
};
