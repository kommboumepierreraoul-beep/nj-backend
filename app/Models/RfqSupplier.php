<?php

namespace App\Models;

use App\Enums\RfqSupplierStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rfq_id', 'supplier_id', 'status', 'sent_at', 'response_date', 'notes'])]
class RfqSupplier extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => RfqSupplierStatus::class,
            'sent_at' => 'datetime',
            'response_date' => 'date',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RfqSupplierQuote::class);
    }
}
