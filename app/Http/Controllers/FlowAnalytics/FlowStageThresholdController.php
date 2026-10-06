<?php

namespace App\Http\Controllers\FlowAnalytics;

use App\Enums\FlowThresholdType;
use App\Enums\FlowType;
use App\Http\Controllers\Controller;
use App\Http\Resources\FlowStageThresholdResource;
use App\Models\AuditLog;
use App\Models\FlowStageThreshold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

// Ecran "Parametres -> Analyse des flux" (Doc/analyse_flux_modele_donnees.md, decision
// §0bis.3, tranchee le 2026-08-26 : aucun seuil en dur) : seuils d'alerte editables sans
// deploiement, meme pattern que CommissionRuleController/ShippingRateController. Chaque
// changement est journalise dans audit_logs, il pilote directement ce qui remonte comme
// "goulot d'etranglement" sur FlowAnalyticsController::bottlenecks().
class FlowStageThresholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $thresholds = FlowStageThreshold::query()
            ->when($request->filled('flow_type'), fn ($query) => $query->where('flow_type', $request->input('flow_type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('flow_type')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => FlowStageThresholdResource::collection($thresholds)]);
    }

    public function show(FlowStageThreshold $flowStageThreshold): JsonResponse
    {
        return response()->json(['data' => new FlowStageThresholdResource($flowStageThreshold)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow_type' => ['required', new Enum(FlowType::class)],
            'stage_code' => [
                'required', 'string', 'max:255',
                Rule::unique('flow_stage_thresholds')->where(fn ($query) => $query->where('flow_type', $request->input('flow_type'))),
            ],
            'label' => ['required', 'string', 'max:255'],
            'threshold_type' => ['required', new Enum(FlowThresholdType::class)],
            'threshold_value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $threshold = FlowStageThreshold::query()->create($validated);

        AuditLog::record(
            'flow_stage_threshold.created',
            $threshold,
            $request->user(),
            null,
            $threshold->only(['flow_type', 'stage_code', 'label', 'threshold_type', 'threshold_value', 'is_active']),
        );

        return response()->json([
            'message' => 'Seuil de flux cree.',
            'data' => new FlowStageThresholdResource($threshold),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, FlowStageThreshold $flowStageThreshold): JsonResponse
    {
        // flow_type/stage_code ne sont volontairement pas editables (identite du seuil,
        // meme logique que CommissionRuleController qui ne permet pas non plus de changer
        // les bornes une fois le palier cree sans passer par une suppression/recreation).
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'threshold_type' => ['sometimes', new Enum(FlowThresholdType::class)],
            'threshold_value' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $flowStageThreshold->only(['label', 'threshold_type', 'threshold_value', 'is_active']);

        $flowStageThreshold->update($validated);

        AuditLog::record(
            'flow_stage_threshold.updated',
            $flowStageThreshold,
            $request->user(),
            $old,
            $flowStageThreshold->only(['label', 'threshold_type', 'threshold_value', 'is_active']),
        );

        return response()->json([
            'message' => 'Seuil de flux mis a jour.',
            'data' => new FlowStageThresholdResource($flowStageThreshold),
        ]);
    }

    public function destroy(FlowStageThreshold $flowStageThreshold): JsonResponse
    {
        $flowStageThreshold->delete();

        return response()->json(['message' => 'Seuil de flux supprime.']);
    }
}
