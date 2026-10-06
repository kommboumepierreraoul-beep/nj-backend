<?php

namespace App\Enums;

enum SalesOrderType: string
{
    case PRODUIT_UNIQUE_MULTI_CHOIX = 'PRODUIT_UNIQUE_MULTI_CHOIX';
    case MULTI_PRODUITS = 'MULTI_PRODUITS';
    case PRESTATION_SERVICE = 'PRESTATION_SERVICE';
}
