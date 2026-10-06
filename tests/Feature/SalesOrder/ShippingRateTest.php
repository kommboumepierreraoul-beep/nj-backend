<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\ShippingMode;
use App\Models\ShippingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ShippingRateTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_the_default_seeded_tiers_are_present(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/shipping-rates', $headers)->assertOk();

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
        $this->assertDatabaseHas('shipping_rates', ['mode' => ShippingMode::AERIEN->value, 'unit' => 'kg']);
        $this->assertDatabaseHas('shipping_rates', ['mode' => ShippingMode::MARITIME->value, 'unit' => 'CBM']);
    }

    public function test_admin_can_create_a_shipping_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/shipping-rates', [
            'mode' => ShippingMode::AERIEN->value,
            'min_quantity' => 0,
            'max_quantity' => null,
            'rate' => 5500,
            'unit' => 'kg',
            'lead_time_label' => '7 à 14 jours',
            'sort_order' => 9,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('shipping_rates', ['rate' => 5500, 'unit' => 'kg']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipping_rate.created', 'entity_type' => 'ShippingRate']);
    }

    public function test_admin_can_update_a_shipping_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rate = ShippingRate::factory()->create(['rate' => 5000]);

        $this->putJson("/api/shipping-rates/{$rate->id}", [
            'rate' => 6200,
        ], $headers)->assertOk();

        $this->assertEquals(6200, (float) $rate->fresh()->rate);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipping_rate.updated', 'entity_type' => 'ShippingRate']);
    }

    public function test_admin_can_deactivate_a_shipping_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rate = ShippingRate::factory()->create(['is_active' => true]);

        $this->putJson("/api/shipping-rates/{$rate->id}", [
            'is_active' => false,
        ], $headers)->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_a_shipping_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rate = ShippingRate::factory()->create();

        $this->deleteJson("/api/shipping-rates/{$rate->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('shipping_rates', ['id' => $rate->id]);
    }

    public function test_manage_permission_is_required_to_create_a_shipping_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('shipping_rates.manage');

        $this->postJson('/api/shipping-rates', [
            'mode' => ShippingMode::AERIEN->value, 'min_quantity' => 0, 'rate' => 1000,
            'unit' => 'kg', 'lead_time_label' => '7 à 14 jours',
        ], $headers)->assertForbidden();
    }

    public function test_view_permission_is_required_to_list_shipping_rates(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('shipping_rates.view');

        $this->getJson('/api/shipping-rates', $headers)->assertForbidden();
    }

    public function test_resolve_for_returns_the_active_tier_matching_the_quantity_and_ignores_inactive_or_out_of_range_tiers(): void
    {
        // Neutralise les paliers seedes par defaut (2026_08_17_000007) pour ne tester que
        // les paliers crees explicitement ci-dessous, notamment l'absence de resultat pour
        // MARITIME.
        ShippingRate::query()->update(['is_active' => false]);

        ShippingRate::factory()->create([
            'mode' => ShippingMode::AERIEN->value,
            'min_quantity' => 0,
            'max_quantity' => 50,
            'rate' => 6000,
            'sort_order' => 0,
        ]);
        ShippingRate::factory()->create([
            'mode' => ShippingMode::AERIEN->value,
            'min_quantity' => 50,
            'max_quantity' => null,
            'rate' => 4500,
            'sort_order' => 1,
        ]);
        // Palier inactif, ne doit jamais etre retourne meme s'il couvre la quantite.
        ShippingRate::factory()->create([
            'mode' => ShippingMode::AERIEN->value,
            'min_quantity' => 0,
            'max_quantity' => null,
            'rate' => 1,
            'is_active' => false,
            'sort_order' => -1,
        ]);

        $this->assertEquals(6000, (float) ShippingRate::resolveFor('AERIEN', 10)->rate);
        $this->assertEquals(4500, (float) ShippingRate::resolveFor('AERIEN', 50)->rate);
        $this->assertEquals(4500, (float) ShippingRate::resolveFor('AERIEN', 200)->rate);
        $this->assertNull(ShippingRate::resolveFor('MARITIME', 10));
    }
}
