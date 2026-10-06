<?php

namespace App\Models;

use App\Enums\ShippingMode;
use Database\Factories\ShippingRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mode', 'min_quantity', 'max_quantity', 'rate', 'unit', 'lead_time_label', 'is_active', 'sort_order'])]
class ShippingRate extends Model
{
    /** @use HasFactory<ShippingRateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mode' => ShippingMode::class,
            'min_quantity' => 'decimal:4',
            'max_quantity' => 'decimal:4',
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Palier actif applicable a une quantite donnee (poids en kg pour AERIEN, volume en
     * CBM pour MARITIME), dans l'ordre sort_order. Utilise a l'emission d'une proforma
     * comparative pour calculer le cout logistique estime, jamais persiste
     * (Doc/proforma_comparatif_addendum.md, decision n°6) — meme pattern que
     * CommissionRule::resolveFor().
     */
    public static function resolveFor(string $mode, float $quantity): ?self
    {
        return static::query()
            ->where('mode', $mode)
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->where(fn ($q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>', $quantity))
            ->orderBy('sort_order')
            ->first();
    }
}
