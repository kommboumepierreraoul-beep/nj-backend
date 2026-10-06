<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode?->value ?? $this->mode,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'rate' => $this->rate,
            'unit' => $this->unit,
            'lead_time_label' => $this->lead_time_label,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
