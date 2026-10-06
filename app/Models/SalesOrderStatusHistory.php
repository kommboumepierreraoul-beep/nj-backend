<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_order_id', 'from_status', 'to_status', 'changed_by_user_id', 'reason'])]
class SalesOrderStatusHistory extends Model
{
    // Le pluriel Eloquent par defaut ("sales_order_status_histories") ne correspond pas
    // au nom de la table cree par la migration (sales_order_status_history, au
    // singulier comme product_price_history) : on le precise explicitement.
    protected $table = 'sales_order_status_history';

    // Journal immuable : pas de colonne updated_at (meme pattern que AuditLog/SystemTrace).
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => SalesOrderStatus::class,
            'to_status' => SalesOrderStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
