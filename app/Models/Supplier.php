<?php

namespace App\Models;

use App\Enums\SupplierReliability;
use App\Enums\SupplierVerificationMethod;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_name', 'legal_name', 'contact_name', 'phone', 'whatsapp', 'wechat_id',
    'alibaba_profile_url', 'email', 'website', 'province', 'city', 'address_line',
    'country_id', 'reliability', 'is_verified', 'verified_at', 'verification_method',
    'payment_terms', 'is_blacklisted', 'blacklist_reason', 'notes', 'is_active',
    'created_by_user_id',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'reliability' => SupplierReliability::class,
            'reliability_score' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'date',
            'verification_method' => SupplierVerificationMethod::class,
            'is_blacklisted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'category_supplier');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(SupplierCommunicationLog::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_supplier')
            ->withPivot(['supplier_sku', 'unit_price', 'currency_id', 'moq', 'lead_time_days', 'is_preferred', 'last_quoted_at', 'notes'])
            ->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
