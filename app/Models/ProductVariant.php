<?php

namespace App\Models;

use App\Enums\VariantLevel;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id', 'sku', 'barcode', 'name', 'level', 'description',
    'proforma_strengths', 'proforma_weaknesses', 'proforma_recommendation',
    'purchase_price', 'purchase_currency_id', 'sale_price', 'sale_currency_id',
    'margin_amount', 'margin_rate', 'estimated_weight_kg', 'estimated_volume_cbm',
    'moq', 'is_recommended', 'is_default', 'is_active', 'sort_order',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'level' => VariantLevel::class,
            // Points forts / points d'attention de la proforma comparative : listes de
            // puces stockees en JSON, reprises telles quelles dans proposal_details a
            // l'emission (Doc/proforma_comparatif_addendum.md).
            'proforma_strengths' => 'array',
            'proforma_weaknesses' => 'array',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'margin_rate' => 'decimal:2',
            'estimated_weight_kg' => 'decimal:3',
            'estimated_volume_cbm' => 'decimal:4',
            'is_recommended' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'purchase_currency_id');
    }

    public function saleCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'sale_currency_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot(['supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred', 'last_quoted_at', 'notes'])
            ->withTimestamps();
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
