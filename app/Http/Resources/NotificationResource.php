<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category?->value ?? $this->category,
            'priority' => $this->priority?->value ?? $this->priority,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'related_entity_type' => $this->related_entity_type,
            'related_entity_id' => $this->related_entity_id,
            'channels_sent' => $this->channels_sent,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
