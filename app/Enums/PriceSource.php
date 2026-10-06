<?php

namespace App\Enums;

enum PriceSource: string
{
    case MANUAL = 'MANUAL';
    case RFQ = 'RFQ';
    case SUPPLIER_UPDATE = 'SUPPLIER_UPDATE';
    case INVOICE = 'INVOICE';
}
