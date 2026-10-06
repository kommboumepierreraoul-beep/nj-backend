<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemTraceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'email_attempted' => $this->email_attempted,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'route' => $this->route,
            'http_method' => $this->http_method,
            'context' => $this->context_json,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
