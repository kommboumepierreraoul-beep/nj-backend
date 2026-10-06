<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use Database\Factories\CompanyPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'label', 'method_type', 'account_number', 'account_holder', 'iban', 'swift',
    'instructions', 'is_active', 'show_on_documents', 'sort_order',
])]
class CompanyPaymentMethod extends Model
{
    /** @use HasFactory<CompanyPaymentMethodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'method_type' => PaymentMethodType::class,
            'is_active' => 'boolean',
            'show_on_documents' => 'boolean',
        ];
    }
}
