<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\CommissionType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\TransportMode;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'client_id', 'type', 'status', 'currency_id', 'billing_mode',
    'subtotal_amount', 'discount_amount', 'commission_rule_id', 'commission_type',
    'commission_rate_applied', 'commission_amount', 'tax_rate', 'tax_amount', 'total_amount',
    'transport_mode', 'estimated_weight_kg',
    'estimated_volume_cbm', 'actual_weight_kg', 'actual_volume_cbm', 'carrier_name',
    'tracking_number', 'order_date', 'validity_days', 'valid_until', 'confirmed_at',
    'shipped_at', 'delivered_at', 'closed_at', 'cancelled_at', 'cancellation_reason',
    'notes', 'internal_notes', 'created_by_user_id',
])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory, SoftDeletes;

    // payment_status n'est jamais assigne en masse : recalcule par
    // SalesOrderPaymentController (via forceFill()->save()), meme logique que
    // Supplier::reliability_score (SupplierEvaluationController).
    // credited_amount (ajout module "factures/proforma", Doc/factures_modele_donnees.md,
    // section 9) suit la meme convention : jamais dans #[Fillable], incremente uniquement
    // via forceFill() par CreditNoteController a chaque avoir emis.

    protected function casts(): array
    {
        return [
            'type' => SalesOrderType::class,
            'status' => SalesOrderStatus::class,
            'payment_status' => SalesOrderPaymentStatus::class,
            'billing_mode' => BillingMode::class,
            'commission_type' => CommissionType::class,
            'transport_mode' => TransportMode::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'commission_rate_applied' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'credited_amount' => 'decimal:2',
            'estimated_weight_kg' => 'decimal:3',
            'estimated_volume_cbm' => 'decimal:4',
            'actual_weight_kg' => 'decimal:3',
            'actual_volume_cbm' => 'decimal:4',
            'order_date' => 'date',
            'valid_until' => 'date',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SalesOrderStatusHistory::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Ajouts module "factures/proforma" (Doc/factures_modele_donnees.md, section 7.1).
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function currentProforma(): ?Invoice
    {
        return $this->invoices()
            ->where('document_type', InvoiceDocumentType::PROFORMA)
            ->whereIn('status', [InvoiceStatus::EMISE, InvoiceStatus::ENVOYEE])
            ->latest('version')
            ->first();
    }

    // Ajout section 9 (facture definitive) : au plus une FACTURE par commande, emise
    // automatiquement par SalesOrderPaymentController au passage a PAYEE — jamais
    // reemise (pas de notion de version multiple pour la FACTURE, contrairement a la
    // PROFORMA).
    public function currentFacture(): ?Invoice
    {
        return $this->invoices()
            ->where('document_type', InvoiceDocumentType::FACTURE)
            ->latest('version')
            ->first();
    }

    // Reçu de paiement tamponné « PAYÉ » (Doc/factures_recu_addendum.md) : au plus un RECU
    // par commande, émis automatiquement au passage à PAYEE en même temps que la FACTURE.
    // Jamais réémis.
    public function currentRecu(): ?Invoice
    {
        return $this->invoices()
            ->where('document_type', InvoiceDocumentType::RECU)
            ->latest('version')
            ->first();
    }
}
