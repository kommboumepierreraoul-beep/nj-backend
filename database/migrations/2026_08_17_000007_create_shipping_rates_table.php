<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Grille tarifaire "transport" configurable (Doc/proforma_comparatif_addendum.md,
    // decision n°6) : meme esprit que commission_rules (paliers min/max, editables sans
    // deploiement depuis un futur ecran Parametres -> Tarifs de transport). mode utilise
    // un nouvel enum ShippingMode (AERIEN/MARITIME), volontairement distinct de
    // TransportMode (AERIEN_STANDARD/AERIEN_SENSIBLE/MARITIME/NON_APPLICABLE, qui qualifie
    // le colis d'une commande) : ici on tarifie uniquement le mode physique d'acheminement,
    // pas la sensibilite du colis. min_quantity/max_quantity portent le poids (kg, AERIEN)
    // ou le volume (CBM, MARITIME) selon le mode de la ligne, meme pattern que
    // commission_rules.min_amount/max_amount (nullable = non borne).
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('mode');
            $table->decimal('min_quantity', 12, 4);
            $table->decimal('max_quantity', 12, 4)->nullable();
            $table->decimal('rate', 12, 2);
            $table->string('unit');
            $table->string('lead_time_label');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed initial : delais de reference repris du cahier des charges
        // (Doc/commandes_modele_donnees.md, section 3.2 : aerien 7-14 j, maritime 45-60 j
        // — la distinction standard/sensible de TransportMode n'a pas d'equivalent ici,
        // deliberement, voir commentaire ci-dessus). Tarifs FCFA/kg et FCFA/CBM : aucune
        // valeur n'est specifiee dans les documents fournis, ceux ci-dessous sont une
        // hypothese de depart plausible, a corriger depuis Parametres -> Tarifs de
        // transport sans nouvelle migration.
        $now = now();

        DB::table('shipping_rates')->insert([
            [
                'mode' => 'AERIEN',
                'min_quantity' => 0,
                'max_quantity' => 50,
                'rate' => 6000,
                'unit' => 'kg',
                'lead_time_label' => '7 à 14 jours',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mode' => 'AERIEN',
                'min_quantity' => 50,
                'max_quantity' => null,
                'rate' => 4500,
                'unit' => 'kg',
                'lead_time_label' => '7 à 14 jours',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mode' => 'MARITIME',
                'min_quantity' => 0,
                'max_quantity' => 2,
                'rate' => 380000,
                'unit' => 'CBM',
                'lead_time_label' => '45 à 60 jours',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mode' => 'MARITIME',
                'min_quantity' => 2,
                'max_quantity' => null,
                'rate' => 320000,
                'unit' => 'CBM',
                'lead_time_label' => '45 à 60 jours',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
