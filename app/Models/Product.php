<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'reference', 'name', 'slug', 'description', 'status',
    'is_sensitive', 'sensitivity_reason', 'default_unit_id', 'default_weight_kg',
    'default_volume_cbm', 'min_order_quantity', 'brand', 'country_of_origin_id',
    'created_by_user_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_sensitive' => 'boolean',
            'default_weight_kg' => 'decimal:3',
            'default_volume_cbm' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'default_unit_id');
    }

    public function countryOfOrigin(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_of_origin_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
