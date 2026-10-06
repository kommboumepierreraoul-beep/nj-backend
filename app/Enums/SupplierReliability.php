<?php

namespace App\Enums;

enum SupplierReliability: string
{
    case INCONNU = 'INCONNU';
    case FAIBLE = 'FAIBLE';
    case MOYEN = 'MOYEN';
    case BON = 'BON';
    case EXCELLENT = 'EXCELLENT';
}
