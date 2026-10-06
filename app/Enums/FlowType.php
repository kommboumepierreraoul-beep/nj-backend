<?php

namespace App\Enums;

// Les 3 flux couverts par le module Analyse des flux (Doc/analyse_flux_modele_donnees.md,
// §0bis) : ACHAT (RFQ -> fournisseur -> commande fournisseur), VENTE (commande client ->
// paiement -> facture -> livraison), ACTIVITE (agregation sur audit_logs/system_traces,
// perimetre ajoute le 2026-08-26). Utilise pour qualifier les seuils d'alerte
// (flow_stage_thresholds) et les endpoints de lecture (FlowAnalyticsController).
enum FlowType: string
{
    case ACHAT = 'ACHAT';
    case VENTE = 'VENTE';
    case ACTIVITE = 'ACTIVITE';
}
