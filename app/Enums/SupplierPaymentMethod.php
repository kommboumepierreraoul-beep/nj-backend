<?php

namespace App\Enums;

enum SupplierPaymentMethod: string
{
    case ALIPAY = 'ALIPAY';
    case WECHAT_PAY = 'WECHAT_PAY';
    case BANK_TRANSFER_CNY = 'BANK_TRANSFER_CNY';
    case WESTERN_UNION = 'WESTERN_UNION';
    case CASH_CHINA = 'CASH_CHINA';
    case OTHER = 'OTHER';
}
