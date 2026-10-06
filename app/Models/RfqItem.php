<?php

namespace App\Models;

use Database\Factories\RfqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rfq_id', 'product_id', 'custom_description', 'target_quantity', 'target_unit_id', 'target_price', 'notes'])]
class RfqItem extends Model
{
    /** @use HasFactory<RfqItemFactory> */
    use HasFactory;

    public $timestamps = false;

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqSupplierQuote::class);
    }
}
