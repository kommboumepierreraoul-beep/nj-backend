<?php

namespace App\Models;

use App\Enums\SalesOrderItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_order_id', 'item_type', 'product_variant_id', 'label', 'description',
    'quantity', 'unit_price', 'discount_amount', 'subtotal', 'is_proposed_option',
    'is_selected', 'sourced_purchase_order_item_id', 'estimated_weight_kg',
    'estimated_volume_cbm', 'notes', 'sort_order',
])]
class SalesOrderItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'item_type' => SalesOrderItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_proposed_option' => 'boolean',
            'is_selected' => 'boolean',
            'estimated_weight_kg' => 'decimal:3',
            'estimated_volume_cbm' => 'decimal:4',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function sourcedPurchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'sourced_purchase_order_item_id');
    }
}
