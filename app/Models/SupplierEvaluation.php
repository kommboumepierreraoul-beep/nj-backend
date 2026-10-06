<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id', 'purchase_order_id', 'evaluated_by_user_id', 'quality_score',
    'communication_score', 'delay_respect_score', 'price_competitiveness_score',
    'comment', 'evaluated_at',
])]
class SupplierEvaluation extends Model
{
    protected function casts(): array
    {
        return ['evaluated_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $evaluation) {
            $evaluation->overall_score = round(collect([
                $evaluation->quality_score,
                $evaluation->communication_score,
                $evaluation->delay_respect_score,
                $evaluation->price_competitiveness_score,
            ])->avg(), 2);
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }
}
