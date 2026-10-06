<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'icon', 'is_active'])]
class ContactChannelType extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'channel_type_id');
    }
}
