<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CompanySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legal_name,
            'tagline' => $this->tagline,
            'address_line' => $this->address_line,
            'representation_line' => $this->representation_line,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'website' => $this->website,
            'logo_path' => $this->logo_path,
            // URL publique servie par Storage::disk('public') — pratique pour l'aperçu
            // dans l'écran Paramètres -> Entreprise sans que le frontend ait à reconstruire
            // le chemin lui-même.
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'default_proforma_validity_days' => $this->default_proforma_validity_days,
            'default_tax_rate' => $this->default_tax_rate,
            // Valeurs par defaut du bloc "Notes / conditions" de la proforma comparative
            // (Doc/proforma_comparatif_addendum.md) — reprises automatiquement a l'emission.
            'default_proforma_conditions' => $this->default_proforma_conditions,
            'default_proforma_production_delay' => $this->default_proforma_production_delay,
            'default_proforma_payment_terms' => $this->default_proforma_payment_terms,
            'default_proforma_customs' => $this->default_proforma_customs,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
