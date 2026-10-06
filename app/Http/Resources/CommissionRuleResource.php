<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'commission_type' => $this->commission_type?->value ?? $this->commission_type,
            'rate_or_amount' => $this->rate_or_amount,
            'currency_id' => $this->currency_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
