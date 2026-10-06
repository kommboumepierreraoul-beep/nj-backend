<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_type' => $this->client_type?->value ?? $this->client_type,
            'full_name' => $this->full_name,
            'legal_name' => $this->legal_name,
            'category_id' => $this->category_id,
            'country_id' => $this->country_id,
            'city' => $this->city,
            'region' => $this->region,
            'address_line' => $this->address_line,
            'preferred_currency_id' => $this->preferred_currency_id,
            'preferred_language' => $this->preferred_language?->value ?? $this->preferred_language,
            'referred_by_client_id' => $this->referred_by_client_id,
            'billing_mode' => $this->billing_mode?->value ?? $this->billing_mode,
            'has_custom_commission' => $this->has_custom_commission,
            'custom_commission_rate' => $this->custom_commission_rate,
            'proforma_validity_days' => $this->proforma_validity_days,
            'value_segment' => $this->value_segment?->value ?? $this->value_segment,
            'status' => $this->status?->value ?? $this->status,
            'internal_notes' => $this->internal_notes,
            'created_by_user_id' => $this->created_by_user_id,
            'category' => new ClientCategoryResource($this->whenLoaded('category')),
            'country' => $this->whenLoaded('country'),
            'preferred_currency' => $this->whenLoaded('preferredCurrency'),
            'referred_by' => new ClientResource($this->whenLoaded('referredBy')),
            'contacts' => $this->whenLoaded('contacts'),
            // Colonne « Contact préféré » de la liste clients (ClientController::index
            // eager-load 'preferredContact.channelType').
            'preferred_contact' => $this->whenLoaded('preferredContact'),
            'tags' => $this->whenLoaded('tags'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
