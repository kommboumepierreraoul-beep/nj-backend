<?php

namespace App\Models;

use App\Enums\FlowThresholdType;
use App\Enums\FlowType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['flow_type', 'stage_code', 'label', 'threshold_type', 'threshold_value', 'is_active', 'sort_order'])]
class FlowStageThreshold extends Model
{
    protected function casts(): array
    {
        return [
            'flow_type' => FlowType::class,
            'threshold_type' => FlowThresholdType::class,
            'threshold_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Seuil actif pour une etape/indicateur donne d'un flux donne
     * (Doc/analyse_flux_modele_donnees.md, §3, endpoint /bottlenecks). Retourne null si
     * aucun seuil configure -- l'etape n'est alors jamais signalee comme goulot.
     */
    public static function resolveFor(FlowType $flowType, string $stageCode): ?self
    {
        return static::query()
            ->where('flow_type', $flowType->value)
            ->where('stage_code', $stageCode)
            ->where('is_active', true)
            ->first();
    }
}
