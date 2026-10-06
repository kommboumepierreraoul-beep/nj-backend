<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'legal_name', 'tagline', 'address_line', 'representation_line', 'phone',
    'whatsapp', 'email', 'website', 'logo_path', 'default_proforma_validity_days',
    'default_tax_rate',
    // Valeurs par defaut du bloc "Notes / conditions" de la proforma comparative
    // (Doc/proforma_comparatif_addendum.md) : reprises automatiquement a l'emission,
    // surchargeables par l'emetteur.
    'default_proforma_conditions', 'default_proforma_production_delay',
    'default_proforma_payment_terms', 'default_proforma_customs',
])]
class CompanySettings extends Model
{
    protected function casts(): array
    {
        return [
            'default_proforma_validity_days' => 'integer',
            'default_tax_rate' => 'decimal:2',
        ];
    }

    /**
     * Table singleton (Doc/proforma_generation_addendum.md, section 2.1) : une seule
     * ligne exploitee (id=1), seedee par la migration de creation.
     */
    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
