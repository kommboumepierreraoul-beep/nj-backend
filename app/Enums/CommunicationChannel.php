<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case WECHAT = 'WECHAT';
    case ALIBABA = 'ALIBABA';
    case WHATSAPP = 'WHATSAPP';
    case EMAIL = 'EMAIL';
    case PHONE = 'PHONE';
    case IN_PERSON = 'IN_PERSON';
    case OTHER = 'OTHER';
}
