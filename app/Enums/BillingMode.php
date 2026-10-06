<?php

namespace App\Enums;

enum BillingMode: string
{
    case COMMISSION_VISIBLE = 'COMMISSION_VISIBLE';
    case PRIX_GLOBAL = 'PRIX_GLOBAL';
}
