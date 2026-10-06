<?php

namespace App\Enums;

enum SupplierDocumentType: string
{
    case BUSINESS_LICENSE = 'BUSINESS_LICENSE';
    case CERTIFICATE_ISO = 'CERTIFICATE_ISO';
    case CERTIFICATE_BSCI = 'CERTIFICATE_BSCI';
    case FACTORY_AUDIT_REPORT = 'FACTORY_AUDIT_REPORT';
    case OTHER = 'OTHER';
}
