<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'method_type' => $this->method_type?->value ?? $this->method_type,
            'account_number' => $this->account_number,
            'account_holder' => $this->account_holder,
            'iban' => $this->iban,
            'swift' => $this->swift,
            'instructions' => $this->instructions,
            'is_active' => $this->is_active,
            'show_on_documents' => $this->show_on_documents,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
