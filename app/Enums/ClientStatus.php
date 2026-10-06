<?php

namespace App\Enums;

enum ClientStatus: string
{
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
    case VIP = 'VIP';
    case BLOQUE = 'BLOQUE';
}
