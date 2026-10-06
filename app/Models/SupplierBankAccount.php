<?php

namespace App\Models;

use App\Enums\SupplierPaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'method', 'account_name', 'account_number', 'bank_name', 'swift_code', 'currency_id', 'is_default', 'is_active'])]
class SupplierBankAccount extends Model
{
    protected function casts(): array
    {
        return [
            'method' => SupplierPaymentMethod::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
