<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'old_value' => $this->old_value_json,
            'new_value' => $this->new_value_json,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'full_name' => $this->actor->full_name,
                'email' => $this->actor->email,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
