<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'level' => $this->level?->value ?? $this->level,
            'description' => $this->description,
            // Arguments repris automatiquement dans la proforma comparative
            // (Doc/proforma_comparatif_addendum.md).
            'proforma_strengths' => $this->proforma_strengths ?? [],
            'proforma_weaknesses' => $this->proforma_weaknesses ?? [],
            'proforma_recommendation' => $this->proforma_recommendation,
            'purchase_price' => $this->purchase_price,
            'purchase_currency_id' => $this->purchase_currency_id,
            'sale_price' => $this->sale_price,
            'sale_currency_id' => $this->sale_currency_id,
            'margin_amount' => $this->margin_amount,
            'margin_rate' => $this->margin_rate,
            'estimated_weight_kg' => $this->estimated_weight_kg,
            'estimated_volume_cbm' => $this->estimated_volume_cbm,
            'moq' => $this->moq,
            'is_recommended' => $this->is_recommended,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'purchase_currency' => $this->whenLoaded('purchaseCurrency'),
            'sale_currency' => $this->whenLoaded('saleCurrency'),
            'attribute_values' => $this->whenLoaded('attributeValues'),
            'suppliers' => $this->whenLoaded('suppliers'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
