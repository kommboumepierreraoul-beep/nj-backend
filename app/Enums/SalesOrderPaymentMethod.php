<?php

namespace App\Enums;

enum SalesOrderPaymentMethod: string
{
    case ORANGE_MONEY = 'ORANGE_MONEY';
    case VIREMENT_UBA = 'VIREMENT_UBA';
    case WAVE = 'WAVE';
    case MTN_MOMO = 'MTN_MOMO';
    case ESPECES = 'ESPECES';
    case AUTRE = 'AUTRE';
}
