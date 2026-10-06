<?php

namespace App\Models;

use App\Enums\CommissionType;
use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'min_amount', 'max_amount', 'commission_type', 'rate_or_amount', 'currency_id', 'is_active', 'sort_order'])]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate_or_amount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * Palier actif applicable a un montant donne, dans l'ordre sort_order.
     * Utilise a la creation d'une commande si le client n'a pas de taux
     * personnalise (clients.has_custom_commission), voir §2.2 du document
     * de modele de donnees (Doc/commandes_modele_donnees.md).
     */
    public static function resolveFor(float $amount): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>', $amount))
            ->orderBy('sort_order')
            ->first();
    }
}
