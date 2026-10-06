<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\CommissionType;
use App\Models\CommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class CommissionRuleTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_the_default_seeded_tiers_are_present(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/commission-rules', $headers)->assertOk();

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
        $this->assertDatabaseHas('commission_rules', ['label' => 'Forfait standard (< 100 000 FCFA)', 'commission_type' => CommissionType::FORFAIT->value]);
        $this->assertDatabaseHas('commission_rules', ['label' => 'Taux standard (>= 100 000 FCFA)', 'commission_type' => CommissionType::POURCENTAGE->value]);
    }

    public function test_admin_can_create_a_commission_rule(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/commission-rules', [
            'label' => 'Palier VIP',
            'min_amount' => 500000,
            'max_amount' => null,
            'commission_type' => CommissionType::POURCENTAGE->value,
            'rate_or_amount' => 7.5,
            'sort_order' => 2,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('commission_rules', ['label' => 'Palier VIP']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'commission_rule.created', 'entity_type' => 'CommissionRule']);
    }

    public function test_admin_can_update_a_commission_rule(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rule = CommissionRule::factory()->create(['rate_or_amount' => 10]);

        $this->putJson("/api/commission-rules/{$rule->id}", [
            'rate_or_amount' => 12,
        ], $headers)->assertOk();

        $this->assertEquals(12, (float) $rule->fresh()->rate_or_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'commission_rule.updated', 'entity_type' => 'CommissionRule']);
    }

    public function test_admin_can_deactivate_a_commission_rule(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rule = CommissionRule::factory()->create(['is_active' => true]);

        $this->putJson("/api/commission-rules/{$rule->id}", [
            'is_active' => false,
        ], $headers)->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_a_commission_rule(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rule = CommissionRule::factory()->create();

        $this->deleteJson("/api/commission-rules/{$rule->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('commission_rules', ['id' => $rule->id]);
    }

    public function test_manage_permission_is_required_to_create_a_commission_rule(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('commission_rules.manage');

        $this->postJson('/api/commission-rules', [
            'label' => 'X', 'min_amount' => 0, 'commission_type' => CommissionType::FORFAIT->value, 'rate_or_amount' => 1000,
        ], $headers)->assertForbidden();
    }

    public function test_view_permission_is_required_to_list_commission_rules(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('commission_rules.view');

        $this->getJson('/api/commission-rules', $headers)->assertForbidden();
    }
}
