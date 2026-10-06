<?php

namespace App\Enums;

enum VariantLevel: string
{
    case PREMIER_CHOIX = 'PREMIER_CHOIX';
    case DEUXIEME_CHOIX = 'DEUXIEME_CHOIX';
    case TROISIEME_CHOIX = 'TROISIEME_CHOIX';
    case STANDARD = 'STANDARD';
}
