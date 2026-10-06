<?php

namespace App\Enums;

enum UnitType: string
{
    case PIECE = 'PIECE';
    case CARTON = 'CARTON';
    case LOT = 'LOT';
    case KG = 'KG';
    case CBM = 'CBM';
    case METRE = 'METRE';
    case LITRE = 'LITRE';
}
