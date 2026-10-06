<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rfq_supplier_id', 'rfq_item_id', 'quoted_unit_price', 'currency_id', 'quoted_moq', 'quoted_lead_time_days', 'is_selected', 'notes', 'quoted_at'])]
class RfqSupplierQuote extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'quoted_at' => 'datetime',
        ];
    }

    public function rfqSupplier(): BelongsTo
    {
        return $this->belongsTo(RfqSupplier::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
