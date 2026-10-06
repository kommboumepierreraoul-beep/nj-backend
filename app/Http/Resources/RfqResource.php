<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'requested_by_user_id' => $this->requested_by_user_id,
            'status' => $this->status?->value ?? $this->status,
            'request_date' => $this->request_date,
            'expected_response_date' => $this->expected_response_date,
            'notes' => $this->notes,
            // Compteurs pour la liste (RfqController::index -> withCount) : la cle
            // 'suppliers_count' est celle attendue par le frontend, alimentee par le
            // compteur Eloquent 'rfq_suppliers_count'.
            'items_count' => $this->whenCounted('items'),
            'suppliers_count' => $this->whenCounted('rfqSuppliers'),
            'items' => $this->whenLoaded('items'),
            'rfq_suppliers' => $this->whenLoaded('rfqSuppliers'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
