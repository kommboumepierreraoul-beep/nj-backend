<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'supplier_id' => $this->supplier_id,
            // La colonne 'invoice_id' a ete renommee 'sales_order_id' par la migration
            // 2026_08_16_000012 ; on expose desormais le bon nom.
            'sales_order_id' => $this->sales_order_id,
            'rfq_id' => $this->rfq_id,
            'rfq_supplier_quote_id' => $this->rfq_supplier_quote_id,
            'status' => $this->status?->value ?? $this->status,
            'order_date' => $this->order_date,
            'expected_delivery_date' => $this->expected_delivery_date,
            'actual_delivery_date' => $this->actual_delivery_date,
            'total_amount' => $this->total_amount,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency'),
            'notes' => $this->notes,
            'created_by_user_id' => $this->created_by_user_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'items' => $this->whenLoaded('items'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
