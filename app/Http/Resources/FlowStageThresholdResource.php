<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowStageThresholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flow_type' => $this->flow_type?->value ?? $this->flow_type,
            'stage_code' => $this->stage_code,
            'label' => $this->label,
            'threshold_type' => $this->threshold_type?->value ?? $this->threshold_type,
            'threshold_value' => $this->threshold_value,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
