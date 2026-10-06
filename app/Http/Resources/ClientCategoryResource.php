<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'badge_color' => $this->badge_color,
            'badge_image_path' => $this->badge_image_path,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
