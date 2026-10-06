<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Modèle explicite pour la table pivot product_supplier (elle porte trop de
// données métier — prix, MOQ, délai — pour rester un pivot anonyme).
#[Fillable(['product_variant_id', 'supplier_id', 'supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred', 'last_quoted_at', 'notes'])]
class ProductSupplier extends Model
{
    protected $table = 'product_supplier';

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_preferred' => 'boolean',
            'last_quoted_at' => 'date',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
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
