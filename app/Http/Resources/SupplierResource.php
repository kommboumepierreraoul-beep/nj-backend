<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'legal_name' => $this->legal_name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'wechat_id' => $this->wechat_id,
            'alibaba_profile_url' => $this->alibaba_profile_url,
            'email' => $this->email,
            'website' => $this->website,
            'province' => $this->province,
            'city' => $this->city,
            'address_line' => $this->address_line,
            'country_id' => $this->country_id,
            'reliability' => $this->reliability?->value ?? $this->reliability,
            'reliability_score' => $this->reliability_score,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at,
            'verification_method' => $this->verification_method?->value ?? $this->verification_method,
            'payment_terms' => $this->payment_terms,
            'is_blacklisted' => $this->is_blacklisted,
            'blacklist_reason' => $this->blacklist_reason,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_by_user_id' => $this->created_by_user_id,
            'country' => $this->whenLoaded('country'),
            'contacts' => $this->whenLoaded('contacts'),
            'categories' => $this->whenLoaded('categories'),
            'bank_accounts' => $this->whenLoaded('bankAccounts'),
            'documents' => $this->whenLoaded('documents'),
            'evaluations' => $this->whenLoaded('evaluations'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
