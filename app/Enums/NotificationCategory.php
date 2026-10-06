<?php

namespace App\Enums;

// Catégorie d'une notification interne (Doc/notifications_modele_donnees.md, §3.2/§3.3) :
// pilote les préférences par utilisateur (notification_preferences) et les filtres de la
// page Notifications. Une notification appartient à une seule catégorie.
enum NotificationCategory: string
{
    case COMMANDE = 'COMMANDE';
    case ACHAT = 'ACHAT';
    case PAIEMENT = 'PAIEMENT';
    case RELANCE = 'RELANCE';
    case FLUX = 'FLUX';
    case SECURITE = 'SECURITE';

    /**
     * Valeur par défaut de préférence quand l'utilisateur n'a jamais personnalisé cette
     * catégorie (Doc/notifications_modele_donnees.md, §3.3). Un seul canal désactivable
     * (email) — le canal in-app est toujours actif, voir NotificationPreference::resolveFor().
     *
     * @return array{email: bool}
     */
    public function defaultChannels(): array
    {
        return ['email' => true];
    }
}
