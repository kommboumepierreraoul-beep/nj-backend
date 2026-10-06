<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'full_name', 'role_title', 'phone', 'wechat_id', 'email', 'is_primary', 'notes'])]
class SupplierContact extends Model
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
