<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\CommissionType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Models\Client;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_sales_orders(): void
    {
        $this->getJson('/api/sales-orders')->assertUnauthorized();
    }

    public function test_admin_can_create_a_multi_product_sales_order_with_an_auto_generated_reference(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $variant = ProductVariant::factory()->create();

        $response = $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::MULTI_PRODUITS->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'PRODUIT', 'product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 5000],
            ],
        ], $headers)->assertCreated();

        $this->assertNotEmpty($response->json('data.reference'));
        $this->assertStringStartsWith('NJG-', $response->json('data.reference'));
        $this->assertDatabaseHas('sales_orders', [
            'client_id' => $client->id,
            'created_by_user_id' => $admin->id,
            'subtotal_amount' => 10000,
        ]);
        $this->assertDatabaseHas('sales_order_status_history', [
            'sales_order_id' => $response->json('data.id'),
            'from_status' => null,
            'to_status' => SalesOrderStatus::BROUILLON->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'SalesOrder',
            'entity_id' => $response->json('data.id'),
            'action' => 'sales_order.created',
        ]);
    }

    public function test_admin_can_create_a_multi_choice_sales_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'PRODUIT', 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 20000, 'is_proposed_option' => true, 'is_selected' => true],
            ],
        ], $headers)->assertCreated()->assertJsonPath('data.type', SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX->value);
    }

    public function test_admin_can_create_a_service_only_sales_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'SERVICE', 'label' => 'Sourcing fournisseur', 'quantity' => 1, 'unit_price' => 50000],
            ],
        ], $headers)->assertCreated()->assertJsonPath('data.type', SalesOrderType::PRESTATION_SERVICE->value);
    }

    public function test_a_service_item_requires_a_label(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'SERVICE', 'quantity' => 1, 'unit_price' => 50000],
            ],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['items.0.label']);
    }

    public function test_commission_uses_the_flat_fee_tier_below_the_threshold(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        CommissionRule::query()->create(['label' => 'Forfait', 'min_amount' => 0, 'max_amount' => 100000, 'commission_type' => CommissionType::FORFAIT->value, 'rate_or_amount' => 10000, 'sort_order' => 0]);
        CommissionRule::query()->create(['label' => 'Taux', 'min_amount' => 100000, 'max_amount' => null, 'commission_type' => CommissionType::POURCENTAGE->value, 'rate_or_amount' => 10, 'sort_order' => 1]);

        $response = $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [['item_type' => 'SERVICE', 'label' => 'Sourcing', 'quantity' => 1, 'unit_price' => 50000]],
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.commission_type', CommissionType::FORFAIT->value);
        $this->assertEquals(10000, $response->json('data.commission_amount'));
        $this->assertEquals(60000, $response->json('data.total_amount'));
    }

    public function test_commission_uses_the_percentage_tier_above_the_threshold(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        CommissionRule::query()->create(['label' => 'Forfait', 'min_amount' => 0, 'max_amount' => 100000, 'commission_type' => CommissionType::FORFAIT->value, 'rate_or_amount' => 10000, 'sort_order' => 0]);
        CommissionRule::query()->create(['label' => 'Taux', 'min_amount' => 100000, 'max_amount' => null, 'commission_type' => CommissionType::POURCENTAGE->value, 'rate_or_amount' => 10, 'sort_order' => 1]);

        $response = $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [['item_type' => 'SERVICE', 'label' => 'Sourcing', 'quantity' => 1, 'unit_price' => 200000]],
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.commission_type', CommissionType::POURCENTAGE->value);
        $this->assertEquals(20000, $response->json('data.commission_amount'));
        $this->assertEquals(220000, $response->json('data.total_amount'));
    }

    public function test_client_custom_commission_rate_overrides_the_default_rules(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create(['has_custom_commission' => true, 'custom_commission_rate' => 5]);
        $currency = Currency::factory()->create();
        CommissionRule::query()->create(['label' => 'Taux', 'min_amount' => 0, 'max_amount' => null, 'commission_type' => CommissionType::POURCENTAGE->value, 'rate_or_amount' => 10, 'sort_order' => 0]);

        $response = $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [['item_type' => 'SERVICE', 'label' => 'Sourcing', 'quantity' => 1, 'unit_price' => 200000]],
        ], $headers)->assertCreated();

        $this->assertEquals(5, $response->json('data.commission_rate_applied'));
        $this->assertEquals(10000, $response->json('data.commission_amount'));
        $this->assertNull($response->json('data.commission_rule_id'));
    }

    public function test_creation_fails_when_no_commission_rule_covers_the_amount(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CommissionRule::query()->delete();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
            'items' => [['item_type' => 'SERVICE', 'label' => 'Sourcing', 'quantity' => 1, 'unit_price' => 50000]],
        ], $headers)->assertStatus(422);
    }

    public function test_status_change_writes_a_status_history_row_and_an_audit_log(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $salesOrder = SalesOrder::factory()->create();

        $this->putJson("/api/sales-orders/{$salesOrder->id}/status", [
            'status' => SalesOrderStatus::CONFIRMEE->value,
        ], $headers)->assertOk()->assertJsonPath('data.status', SalesOrderStatus::CONFIRMEE->value);

        $this->assertDatabaseHas('sales_order_status_history', [
            'sales_order_id' => $salesOrder->id,
            'from_status' => SalesOrderStatus::BROUILLON->value,
            'to_status' => SalesOrderStatus::CONFIRMEE->value,
            'changed_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'SalesOrder',
            'entity_id' => $salesOrder->id,
            'action' => 'sales_order.status_changed',
        ]);

        $this->assertNotNull($salesOrder->fresh()->confirmed_at);
    }

    public function test_cancelling_a_sales_order_requires_a_reason(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = SalesOrder::factory()->create();

        $this->putJson("/api/sales-orders/{$salesOrder->id}/status", [
            'status' => SalesOrderStatus::ANNULEE->value,
        ], $headers)->assertStatus(422);

        $this->putJson("/api/sales-orders/{$salesOrder->id}/status", [
            'status' => SalesOrderStatus::ANNULEE->value,
            'reason' => 'Client injoignable',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('sales_orders', [
            'id' => $salesOrder->id,
            'status' => SalesOrderStatus::ANNULEE->value,
            'cancellation_reason' => 'Client injoignable',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'SalesOrder',
            'entity_id' => $salesOrder->id,
            'action' => 'sales_order.cancelled',
        ]);
    }

    public function test_sending_the_proforma_requires_at_least_one_selected_item(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = SalesOrder::factory()->create();

        $this->putJson("/api/sales-orders/{$salesOrder->id}/status", [
            'status' => SalesOrderStatus::PROFORMA_ENVOYEE->value,
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_delete_a_sales_order_which_is_soft_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = SalesOrder::factory()->create();

        $this->deleteJson("/api/sales-orders/{$salesOrder->id}", [], $headers)->assertOk();

        $this->assertSoftDeleted('sales_orders', ['id' => $salesOrder->id]);
    }

    public function test_manage_permission_is_required_to_create_a_sales_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('sales_orders.manage');
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/sales-orders', [
            'client_id' => $client->id,
            'type' => SalesOrderType::PRESTATION_SERVICE->value,
            'currency_id' => $currency->id,
            'order_date' => now()->toDateString(),
        ], $headers)->assertForbidden();
    }
}
