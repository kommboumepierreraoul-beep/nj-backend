<?php

namespace App\Enums;

enum RfqSupplierStatus: string
{
    case PENDING = 'PENDING';
    case RESPONDED = 'RESPONDED';
    case DECLINED = 'DECLINED';
    case EXPIRED = 'EXPIRED';
}
