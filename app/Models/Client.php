<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\ClientLanguage;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\ValueSegment;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'client_type', 'full_name', 'legal_name', 'category_id', 'country_id', 'city', 'region',
    'address_line', 'preferred_currency_id', 'preferred_language', 'referred_by_client_id',
    'billing_mode', 'has_custom_commission', 'custom_commission_rate',
    'proforma_validity_days', 'value_segment', 'status', 'internal_notes',
    'created_by_user_id',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
            'preferred_language' => ClientLanguage::class,
            'billing_mode' => BillingMode::class,
            'has_custom_commission' => 'boolean',
            'custom_commission_rate' => 'decimal:2',
            'value_segment' => ValueSegment::class,
            'status' => ClientStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function preferredCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'preferred_currency_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_by_client_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Client::class, 'referred_by_client_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    // Contact « préféré » du client (au plus un, garanti par ClientContactController) :
    // vraie relation pour être eager-loadable dans la liste (ClientController::index)
    // et exposée par ClientResource — la colonne « Contact préféré » de la liste
    // clients en dépend.
    public function preferredContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_preferred', true);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_tag');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Ajout module "factures/proforma" (Doc/factures_modele_donnees.md, section 7.1).
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
