<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'client_id' => $this->client_id,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'currency_id' => $this->currency_id,
            'billing_mode' => $this->billing_mode?->value ?? $this->billing_mode,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'commission_rule_id' => $this->commission_rule_id,
            'commission_type' => $this->commission_type?->value ?? $this->commission_type,
            'commission_rate_applied' => $this->commission_rate_applied,
            'commission_amount' => $this->commission_amount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'transport_mode' => $this->transport_mode?->value ?? $this->transport_mode,
            'estimated_weight_kg' => $this->estimated_weight_kg,
            'estimated_volume_cbm' => $this->estimated_volume_cbm,
            'actual_weight_kg' => $this->actual_weight_kg,
            'actual_volume_cbm' => $this->actual_volume_cbm,
            'carrier_name' => $this->carrier_name,
            'tracking_number' => $this->tracking_number,
            'order_date' => $this->order_date,
            'validity_days' => $this->validity_days,
            'valid_until' => $this->valid_until,
            'confirmed_at' => $this->confirmed_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'closed_at' => $this->closed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,
            'created_by_user_id' => $this->created_by_user_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'currency' => $this->whenLoaded('currency'),
            'commission_rule' => $this->whenLoaded('commissionRule'),
            'items' => $this->whenLoaded('items'),
            'payments' => SalesOrderPaymentResource::collection($this->whenLoaded('payments')),
            'status_history' => $this->whenLoaded('statusHistory'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
