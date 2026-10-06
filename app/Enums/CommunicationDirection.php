<?php

namespace App\Enums;

enum CommunicationDirection: string
{
    case INCOMING = 'INCOMING';
    case OUTGOING = 'OUTGOING';
}
