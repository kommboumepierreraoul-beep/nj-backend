<?php

namespace App\Enums;

enum RFQStatus: string
{
    case BROUILLON = 'BROUILLON';
    case ENVOYE = 'ENVOYE';
    case REPONDU = 'REPONDU';
    case EXPIRE = 'EXPIRE';
    case ANNULE = 'ANNULE';
}
