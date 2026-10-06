<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_order_id', 'direction', 'invoice_id', 'amount', 'currency_id', 'payment_method', 'external_reference',
    'receipt_number', 'is_voided', 'voided_reason', 'voided_at', 'recorded_by_user_id',
    'paid_at', 'notes',
])]
class SalesOrderPayment extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'payment_method' => SalesOrderPaymentMethod::class,
            'amount' => 'decimal:2',
            'is_voided' => 'boolean',
            'voided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    // Document Invoice auquel ce mouvement se rattache (ex : un remboursement consecutif a
    // un avoir) — nullable, un encaissement ordinaire n'en a pas (Doc/factures_modele_donnees.md,
    // section 9). Contrepartie de Invoice::refundPayments().
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
