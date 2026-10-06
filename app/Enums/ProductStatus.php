<?php

namespace App\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
