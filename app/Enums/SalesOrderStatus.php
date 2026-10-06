<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case BROUILLON = 'BROUILLON';
    case PROFORMA_ENVOYEE = 'PROFORMA_ENVOYEE';
    case CONFIRMEE = 'CONFIRMEE';
    case EN_PREPARATION = 'EN_PREPARATION';
    case EXPEDIEE = 'EXPEDIEE';
    case LIVREE = 'LIVREE';
    case CLOTUREE = 'CLOTUREE';
    case ANNULEE = 'ANNULEE';
}
