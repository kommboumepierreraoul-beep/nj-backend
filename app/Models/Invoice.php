<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\DocumentLanguage;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\TransportMode;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'invoice_number', 'sales_order_id', 'document_type', 'version', 'status',
    'supersedes_invoice_id', 'credits_invoice_id', 'client_id', 'client_name',
    'client_address', 'client_tax_id', 'currency_id', 'language', 'billing_mode',
    'subtotal_amount', 'discount_amount', 'commission_amount', 'tax_rate', 'tax_amount',
    'total_amount', 'transport_mode', 'legal_mentions', 'due_date', 'currency_equivalents',
    'proposal_details', 'issued_at', 'sent_at', 'cancelled_at', 'cancellation_reason',
    'issued_by_user_id', 'notes',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    // Pas de SoftDeletes : un document emis n'est jamais supprime, seulement
    // ANNULEE ou REMPLACEE (voir Doc/factures_modele_donnees.md, section 2). Aucune
    // colonne n'est modifiee apres issued_at hors status/sent_at/cancelled_at/
    // cancellation_reason (immutabilite a faire respecter cote controleur).
    protected function casts(): array
    {
        return [
            'document_type' => InvoiceDocumentType::class,
            'status' => InvoiceStatus::class,
            'language' => DocumentLanguage::class,
            'billing_mode' => BillingMode::class,
            'transport_mode' => TransportMode::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'due_date' => 'date',
            // Snapshot fige a l'emission (Doc/proforma_generation_addendum.md, section 1) :
            // ajout par rapport au §7 de Doc/factures_modele_donnees.md.
            'currency_equivalents' => 'array',
            // Textes libres saisis a l'emission d'une proforma comparative (Doc/proforma_
            // comparatif_addendum.md, decision n°5) : points forts/attention/recommandation
            // par option + notes/conditions. Meme pattern que currency_equivalents.
            'proposal_details' => 'array',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'supersedes_invoice_id');
    }

    public function credits(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credits_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Nom conserve tel quel (defini par une session anterieure) pour ne rien casser qui en
    // deprendrait deja — voir payments() ci-dessous, alias plus explicite ajoute le
    // 2026-08-21 pour SalesOrderPaymentController::storeForInvoice()/indexForInvoice()
    // (Doc/factures_modele_donnees.md, section 10) : malgre son nom, cette relation ne
    // filtre pas sur sales_order_payments.direction — elle renvoie TOUT mouvement
    // (encaissement ou remboursement) dont invoice_id pointe vers ce document.
    public function refundPayments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    // Alias de refundPayments() avec un nom qui reflete l'usage reel (tout mouvement
    // rattache a ce document, pas seulement des remboursements) — voir le commentaire
    // ci-dessus.
    public function payments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
