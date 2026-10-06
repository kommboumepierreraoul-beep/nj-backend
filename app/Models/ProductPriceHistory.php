<?php

namespace App\Models;

use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_variant_id', 'supplier_id', 'price', 'currency_id', 'source', 'effective_date', 'recorded_by_user_id'])]
class ProductPriceHistory extends Model
{
    // Le pluriel Eloquent par defaut ("product_price_histories") ne correspond pas
    // au nom de la table cree par la migration (product_price_history, au singulier
    // comme product_supplier) : on le precise explicitement.
    protected $table = 'product_price_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'source' => PriceSource::class,
            'effective_date' => 'date',
            'created_at' => 'datetime',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
