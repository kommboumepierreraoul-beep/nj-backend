<?php

namespace App\Models;

use App\Enums\RFQStatus;
use Database\Factories\RfqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['reference', 'requested_by_user_id', 'status', 'request_date', 'expected_response_date', 'notes'])]
class Rfq extends Model
{
    /** @use HasFactory<RfqFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RFQStatus::class,
            'request_date' => 'date',
            'expected_response_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function rfqSuppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    public function suppliers(): HasManyThrough
    {
        return $this->hasManyThrough(Supplier::class, RfqSupplier::class, 'rfq_id', 'id', 'id', 'supplier_id');
    }
}
