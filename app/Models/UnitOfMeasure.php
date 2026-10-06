<?php

namespace App\Models;

use App\Enums\UnitType;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'type'])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    // Le pluriel Eloquent par defaut ("unit_of_measures") ne correspond pas au nom
    // de la table cree par la migration (units_of_measure) : on le precise.
    protected $table = 'units_of_measure';

    protected function casts(): array
    {
        return ['type' => UnitType::class];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'default_unit_id');
    }
}
