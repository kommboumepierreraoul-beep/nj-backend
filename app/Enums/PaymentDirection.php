<?php

namespace App\Enums;

// Sens d'un mouvement dans sales_order_payments (Doc/factures_modele_donnees.md,
// section 9). ENCAISSEMENT est le comportement d'origine (valeur par defaut en base) ;
// REMBOURSEMENT restitue au client tout ou partie d'un encaissement deja recu, sans
// jamais alterer les lignes ENCAISSEMENT existantes (immuables une fois enregistrees).
enum PaymentDirection: string
{
    case ENCAISSEMENT = 'ENCAISSEMENT';
    case REMBOURSEMENT = 'REMBOURSEMENT';
}
