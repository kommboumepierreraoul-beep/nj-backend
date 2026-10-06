<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'supplier_id', 'sales_order_id', 'rfq_id', 'rfq_supplier_quote_id',
    'status', 'order_date', 'expected_delivery_date', 'actual_delivery_date', 'total_amount',
    'currency_id', 'notes', 'created_by_user_id',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // sales_order_id pointe desormais reellement vers sales_orders.id
    // (Doc/commandes_modele_donnees.md, decision ouverte n°5, retenue) : la colonne
    // s'appelait invoice_id et n'avait aucune contrainte FK tant que ce module
    // n'existait pas (voir la migration
    // 2026_08_16_000012_repoint_purchase_orders_invoice_id_to_sales_orders).
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    // Liaison RFQ -> commande fournisseur (Doc/analyse_flux_modele_donnees.md, §8.4) :
    // renseignee quand la commande nait d'un devis retenu. Sert au taux de transformation
    // RFQ -> commande dans FlowAnalyticsController.
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function rfqSupplierQuote(): BelongsTo
    {
        return $this->belongsTo(RfqSupplierQuote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    // Historique du flux achat (Doc/analyse_flux_modele_donnees.md, decision §0bis.1,
    // ajoute le 2026-08-26) : miroir de SalesOrder::statusHistory(), alimente par
    // Supplier\PurchaseOrderController::store()/update(). Sert au module Analyse des flux
    // (FlowAnalyticsController::buildPurchaseFlow()) pour le temps moyen passe par etape.
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }
}
