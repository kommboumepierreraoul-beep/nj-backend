<?php

namespace App\Enums;

enum TransportMode: string
{
    case AERIEN_STANDARD = 'AERIEN_STANDARD';
    case AERIEN_SENSIBLE = 'AERIEN_SENSIBLE';
    case MARITIME = 'MARITIME';
    case NON_APPLICABLE = 'NON_APPLICABLE';
}
