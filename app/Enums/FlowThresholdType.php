<?php

namespace App\Enums;

// Nature du seuil configure sur un flow_stage_thresholds (Doc/analyse_flux_modele_donnees.md,
// §0bis decision 3) : DUREE_JOURS pour un temps moyen passe dans une etape (flux
// achat/vente), COMPTEUR pour un nombre d'occurrences sur la periode (flux activite,
// ex. echecs de connexion).
enum FlowThresholdType: string
{
    case DUREE_JOURS = 'DUREE_JOURS';
    case COMPTEUR = 'COMPTEUR';
}
