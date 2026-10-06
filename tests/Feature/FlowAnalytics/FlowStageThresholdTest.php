<?php

namespace Tests\Feature\FlowAnalytics;

use App\Enums\FlowThresholdType;
use App\Enums\FlowType;
use App\Models\FlowStageThreshold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Ecran "Parametres -> Analyse des flux" (Doc/analyse_flux_modele_donnees.md, decision
 * §0bis.3), App\Http\Controllers\FlowAnalytics\FlowStageThresholdController.
 */
class FlowStageThresholdTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_the_default_seeded_thresholds_are_present(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/flow-stage-thresholds', $headers)->assertOk();

        $this->assertGreaterThanOrEqual(10, count($response->json('data')));
        $this->assertDatabaseHas('flow_stage_thresholds', ['flow_type' => FlowType::ACHAT->value, 'stage_code' => 'SENT']);
        $this->assertDatabaseHas('flow_stage_thresholds', ['flow_type' => FlowType::VENTE->value, 'stage_code' => 'PROFORMA_ENVOYEE']);
        $this->assertDatabaseHas('flow_stage_thresholds', ['flow_type' => FlowType::ACTIVITE->value, 'stage_code' => 'AUTH_LOGIN_FAILED']);
    }

    public function test_admin_can_create_a_threshold(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/flow-stage-thresholds', [
            'flow_type' => FlowType::VENTE->value,
            'stage_code' => 'LIVREE',
            'label' => 'Livree, avant cloture',
            'threshold_type' => FlowThresholdType::DUREE_JOURS->value,
            'threshold_value' => 7,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('flow_stage_thresholds', ['stage_code' => 'LIVREE', 'flow_type' => FlowType::VENTE->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'flow_stage_threshold.created', 'entity_type' => 'FlowStageThreshold']);
    }

    public function test_stage_code_must_be_unique_within_a_flow_type(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/flow-stage-thresholds', [
            'flow_type' => FlowType::ACHAT->value,
            'stage_code' => 'SENT',
            'label' => 'Doublon',
            'threshold_type' => FlowThresholdType::DUREE_JOURS->value,
            'threshold_value' => 1,
        ], $headers)->assertUnprocessable();
    }

    public function test_admin_can_update_a_threshold_value(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $threshold = FlowStageThreshold::query()->where('flow_type', FlowType::ACHAT->value)->where('stage_code', 'SENT')->firstOrFail();

        $this->putJson("/api/flow-stage-thresholds/{$threshold->id}", [
            'threshold_value' => 8,
        ], $headers)->assertOk();

        $this->assertEquals(8, (float) $threshold->fresh()->threshold_value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'flow_stage_threshold.updated', 'entity_type' => 'FlowStageThreshold']);
    }

    public function test_admin_can_delete_a_threshold(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $threshold = FlowStageThreshold::query()->create([
            'flow_type' => FlowType::VENTE->value,
            'stage_code' => 'CLOTUREE',
            'label' => 'Cloturee',
            'threshold_type' => FlowThresholdType::DUREE_JOURS->value,
            'threshold_value' => 1,
        ]);

        $this->deleteJson("/api/flow-stage-thresholds/{$threshold->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('flow_stage_thresholds', ['id' => $threshold->id]);
    }

    public function test_manage_permission_is_required_to_create_a_threshold(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('flow_analytics.manage');

        $this->postJson('/api/flow-stage-thresholds', [
            'flow_type' => FlowType::VENTE->value,
            'stage_code' => 'X',
            'label' => 'X',
            'threshold_type' => FlowThresholdType::DUREE_JOURS->value,
            'threshold_value' => 1,
        ], $headers)->assertForbidden();
    }

    public function test_view_permission_is_required_to_list_thresholds(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('flow_analytics.view');

        $this->getJson('/api/flow-stage-thresholds', $headers)->assertForbidden();
    }
}
