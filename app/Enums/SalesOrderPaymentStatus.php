<?php

namespace App\Enums;

enum SalesOrderPaymentStatus: string
{
    case NON_PAYEE = 'NON_PAYEE';
    case PARTIELLEMENT_PAYEE = 'PARTIELLEMENT_PAYEE';
    case PAYEE = 'PAYEE';
}
