<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Seuils d'alerte du module Analyse des flux (Doc/analyse_flux_modele_donnees.md,
    // decision §0bis.3, tranchee le 2026-08-26 : "aucun seuil en dur", meme esprit que
    // commission_rules/shipping_rates). Remplace ce qu'aurait ete une nouvelle constante
    // PHP a la RELANCE_PROCHE_SEUIL_JOURS de DashboardController -- editable ici depuis un
    // futur ecran Parametres -> Analyse des flux, sans deploiement. Un seuil peut porter sur
    // une duree (temps moyen passe dans une etape, flux ACHAT/VENTE) ou un compteur
    // (occurrences sur la periode, flux ACTIVITE) -- voir App\Enums\FlowThresholdType.
    public function up(): void
    {
        Schema::create('flow_stage_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('flow_type'); // App\Enums\FlowType : ACHAT / VENTE / ACTIVITE
            $table->string('stage_code'); // valeur d'enum de statut (ACHAT/VENTE) ou code d'indicateur (ACTIVITE)
            $table->string('label');
            $table->string('threshold_type'); // App\Enums\FlowThresholdType : DUREE_JOURS / COMPTEUR
            $table->decimal('threshold_value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['flow_type', 'stage_code']);
        });

        // Seed initial, valeurs par defaut ARBITRAIRES faute de reference chiffree dans le
        // cahier des charges (meme prudence que shipping_rates, migration
        // 2026_08_17_000007_create_shipping_rates_table) -- a valider par NJ Global Trade
        // puis ajuster depuis Parametres -> Analyse des flux sans nouvelle migration.
        $now = now();

        DB::table('flow_stage_thresholds')->insert([
            // Flux achat : temps d'attente juge anormal dans chaque etape.
            ['flow_type' => 'ACHAT', 'stage_code' => 'SENT', 'label' => 'RFQ/commande envoyee, en attente de reponse fournisseur', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 5, 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'ACHAT', 'stage_code' => 'CONFIRMED', 'label' => 'Commande fournisseur confirmee, avant mise en production', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 3, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'ACHAT', 'stage_code' => 'IN_PRODUCTION', 'label' => 'En production chez le fournisseur', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 20, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'ACHAT', 'stage_code' => 'SHIPPED', 'label' => 'Expediee, avant reception', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 15, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            // Flux vente : memes etapes que sales_order_status_history.
            ['flow_type' => 'VENTE', 'stage_code' => 'PROFORMA_ENVOYEE', 'label' => 'Proforma envoyee, en attente de confirmation', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 3, 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'VENTE', 'stage_code' => 'CONFIRMEE', 'label' => 'Confirmee, avant preparation', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 2, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'VENTE', 'stage_code' => 'EN_PREPARATION', 'label' => 'En preparation, avant expedition', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 5, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'VENTE', 'stage_code' => 'EXPEDIEE', 'label' => 'Expediee, avant livraison', 'threshold_type' => 'DUREE_JOURS', 'threshold_value' => 10, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            // Flux activite : compteurs sur la periode analysee (Doc/analyse_flux_modele_donnees.md, §1.4).
            ['flow_type' => 'ACTIVITE', 'stage_code' => 'AUTH_LOGIN_FAILED', 'label' => 'Echecs de connexion sur la periode', 'threshold_type' => 'COMPTEUR', 'threshold_value' => 10, 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['flow_type' => 'ACTIVITE', 'stage_code' => 'AUTH_PERMISSION_DENIED', 'label' => 'Acces refuses (permission/role) sur la periode', 'threshold_type' => 'COMPTEUR', 'threshold_value' => 5, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_stage_thresholds');
    }
};
