<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case EMISE = 'EMISE';
    case ENVOYEE = 'ENVOYEE';
    case ANNULEE = 'ANNULEE';
    case REMPLACEE = 'REMPLACEE';
}
