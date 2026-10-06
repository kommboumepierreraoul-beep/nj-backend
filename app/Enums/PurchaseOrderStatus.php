<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'DRAFT';
    case SENT = 'SENT';
    case CONFIRMED = 'CONFIRMED';
    case IN_PRODUCTION = 'IN_PRODUCTION';
    case SHIPPED = 'SHIPPED';
    case RECEIVED = 'RECEIVED';
    case CANCELLED = 'CANCELLED';
}
