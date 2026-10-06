<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_order_id', 'product_variant_id', 'quantity', 'unit_price', 'subtotal', 'notes'])]
class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Tracabilite sourcing optionnelle (Doc/commandes_modele_donnees.md, decision
    // ouverte n°4, retenue) : quelles lignes de commandes clients sont honorees par
    // cet achat fournisseur.
    public function sourcedForSalesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sourced_purchase_order_item_id');
    }
}
