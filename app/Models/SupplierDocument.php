<?php

namespace App\Models;

use App\Enums\SupplierDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'type', 'attachment_id', 'issue_date', 'expiry_date', 'is_verified', 'notes'])]
class SupplierDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SupplierDocumentType::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'is_verified' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
