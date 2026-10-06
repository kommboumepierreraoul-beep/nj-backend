<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case MOBILE_MONEY = 'MOBILE_MONEY';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CASH = 'CASH';
    case OTHER = 'OTHER';
}
