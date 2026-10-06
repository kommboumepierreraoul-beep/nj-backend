<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'badge_color', 'badge_image_path', 'description', 'is_active', 'sort_order'])]
class ClientCategory extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'category_id');
    }
}
