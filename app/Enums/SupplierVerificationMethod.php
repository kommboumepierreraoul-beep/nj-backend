<?php

namespace App\Enums;

enum SupplierVerificationMethod: string
{
    case FACTORY_VISIT = 'FACTORY_VISIT';
    case VIDEO_CALL = 'VIDEO_CALL';
    case THIRD_PARTY_AUDIT = 'THIRD_PARTY_AUDIT';
    case DOCUMENTS_ONLY = 'DOCUMENTS_ONLY';
}
