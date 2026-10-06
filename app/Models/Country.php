<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'iso_code', 'phone_code', 'is_active'])]
class Country extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'country_of_origin_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
