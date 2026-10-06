<?php

namespace App\Models;

use App\Enums\AttributeInputType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'input_type', 'unit_suffix', 'is_filterable'])]
class ProductAttribute extends Model
{
    protected function casts(): array
    {
        return [
            'input_type' => AttributeInputType::class,
            'is_filterable' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
