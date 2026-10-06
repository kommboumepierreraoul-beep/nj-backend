<?php

namespace App\Enums;

// Priorité d'une notification interne (Doc/notifications_modele_donnees.md, §2) : pilote
// l'affichage (badge de couleur côté frontend).
enum NotificationPriority: string
{
    case INFO = 'INFO';
    case IMPORTANT = 'IMPORTANT';
    case CRITIQUE = 'CRITIQUE';
}
