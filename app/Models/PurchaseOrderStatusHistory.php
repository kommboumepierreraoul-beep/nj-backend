<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'from_status', 'to_status', 'changed_by_user_id', 'reason'])]
class PurchaseOrderStatusHistory extends Model
{
    // Meme raisonnement que SalesOrderStatusHistory : le pluriel Eloquent par defaut
    // ("purchase_order_status_histories") ne correspond pas au nom de table cree par la
    // migration (purchase_order_status_history, singulier comme sales_order_status_history
    // / product_price_history).
    protected $table = 'purchase_order_status_history';

    // Journal immuable : pas de colonne updated_at (meme pattern que AuditLog/SystemTrace/
    // SalesOrderStatusHistory).
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => PurchaseOrderStatus::class,
            'to_status' => PurchaseOrderStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
