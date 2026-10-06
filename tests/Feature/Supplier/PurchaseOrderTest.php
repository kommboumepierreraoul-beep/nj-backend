<?php

namespace Tests\Feature\Supplier;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RfqSupplierStatus;
use App\Models\Currency;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqSupplier;
use App\Models\RfqSupplierQuote;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_purchase_order_with_an_auto_generated_reference(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
        ], $headers)->assertCreated();

        $this->assertNotEmpty($response->json('data.reference'));
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $admin->id,
            'total_amount' => 0,
        ]);
    }

    public function test_adding_items_recalculates_the_total_amount(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $variant = ProductVariant::factory()->create();

        $itemId = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/items", [
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'unit_price' => 4.5,
        ], $headers)->assertCreated()->json('data.id');

        $this->assertDatabaseHas('purchase_order_items', ['id' => $itemId, 'subtotal' => 45]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'total_amount' => 45]);

        $secondVariant = ProductVariant::factory()->create();
        $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/items", [
            'product_variant_id' => $secondVariant->id,
            'quantity' => 2,
            'unit_price' => 10,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'total_amount' => 65]);
    }

    public function test_updating_an_item_quantity_recalculates_the_total_amount(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $variant = ProductVariant::factory()->create();
        $item = $purchaseOrder->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 10,
            'subtotal' => 50,
        ]);
        $purchaseOrder->update(['total_amount' => 50]);

        $this->putJson("/api/purchase-orders/{$purchaseOrder->id}/items/{$item->id}", [
            'quantity' => 8,
        ], $headers)->assertOk();

        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'subtotal' => 80]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'total_amount' => 80]);
    }

    public function test_deleting_an_item_recalculates_the_total_amount(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create();
        $variant = ProductVariant::factory()->create();
        $item = $purchaseOrder->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 10,
            'subtotal' => 50,
        ]);
        $purchaseOrder->update(['total_amount' => 50]);

        $this->deleteJson("/api/purchase-orders/{$purchaseOrder->id}/items/{$item->id}", [], $headers)->assertOk();

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'total_amount' => 0]);
    }

    public function test_admin_can_update_purchase_order_status(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::DRAFT->value]);

        $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", [
            'status' => PurchaseOrderStatus::CONFIRMED->value,
        ], $headers)->assertOk()->assertJsonPath('data.status', PurchaseOrderStatus::CONFIRMED->value);
    }

    // Historique du flux achat (Doc/analyse_flux_modele_donnees.md, decision §0bis.1,
    // ajoute le 2026-08-26) : la creation via l'API pose une premiere ligne d'historique,
    // meme pattern que SalesOrderController::store().
    public function test_creating_a_purchase_order_writes_an_initial_status_history_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('purchase_order_status_history', [
            'purchase_order_id' => $response->json('data.id'),
            'from_status' => null,
            'to_status' => PurchaseOrderStatus::DRAFT->value,
        ]);
    }

    // Un changement de statut effectif via l'update() generique doit ecrire une ligne
    // d'historique (ce controleur n'a pas de route updateStatus() dediee, contrairement a
    // SalesOrderController).
    public function test_updating_purchase_order_status_writes_a_status_history_entry(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::DRAFT->value]);

        $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", [
            'status' => PurchaseOrderStatus::SENT->value,
        ], $headers)->assertOk();

        $this->assertDatabaseHas('purchase_order_status_history', [
            'purchase_order_id' => $purchaseOrder->id,
            'from_status' => PurchaseOrderStatus::DRAFT->value,
            'to_status' => PurchaseOrderStatus::SENT->value,
            'changed_by_user_id' => $admin->id,
        ]);
    }

    // Une mise a jour qui ne touche pas au statut ne doit ecrire aucune ligne d'historique
    // (le purchase order est cree directement via la factory ici, pas via l'API, donc sans
    // la ligne initiale de store() -- la table doit rester strictement vide).
    public function test_updating_a_purchase_order_without_changing_status_does_not_write_a_history_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::DRAFT->value]);

        $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", [
            'notes' => 'Une note',
        ], $headers)->assertOk();

        $this->assertDatabaseCount('purchase_order_status_history', 0);
    }

    public function test_admin_can_delete_a_purchase_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $purchaseOrder = PurchaseOrder::factory()->create();

        $this->deleteJson("/api/purchase-orders/{$purchaseOrder->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('purchase_orders', ['id' => $purchaseOrder->id]);
    }

    // Liaison RFQ -> commande fournisseur (Doc/analyse_flux_modele_donnees.md, §8.4) :
    // rfq_id / rfq_supplier_quote_id sont persistables, exposes par la Resource et
    // navigables via les relations rfq() / rfqSupplierQuote().
    public function test_a_purchase_order_can_be_linked_to_an_rfq_and_selected_quote(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $rfq = Rfq::factory()->create();
        $rfqItem = RfqItem::factory()->create(['rfq_id' => $rfq->id]);
        $rfqSupplier = RfqSupplier::query()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'status' => RfqSupplierStatus::RESPONDED->value,
            'sent_at' => now(),
        ]);
        $quote = RfqSupplierQuote::query()->create([
            'rfq_supplier_id' => $rfqSupplier->id,
            'rfq_item_id' => $rfqItem->id,
            'quoted_unit_price' => 4.2,
            'currency_id' => $currency->id,
            'is_selected' => true,
            'quoted_at' => now(),
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'currency_id' => $currency->id,
            'rfq_id' => $rfq->id,
            'rfq_supplier_quote_id' => $quote->id,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'rfq_id' => $rfq->id,
            'rfq_supplier_quote_id' => $quote->id,
        ]);

        $fresh = $purchaseOrder->fresh(['rfq', 'rfqSupplierQuote']);
        $this->assertTrue($fresh->rfq->is($rfq));
        $this->assertTrue($fresh->rfqSupplierQuote->is($quote));

        $this->getJson("/api/purchase-orders/{$purchaseOrder->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.rfq_id', $rfq->id)
            ->assertJsonPath('data.rfq_supplier_quote_id', $quote->id);
    }

    // La regle de validation exists: est bien cablee sur le nouveau champ rfq_id
    // (Supplier\PurchaseOrderController::store()).
    public function test_an_unknown_rfq_id_is_rejected_on_creation(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
            'rfq_id' => 999999,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('rfq_id');
    }

    public function test_manage_permission_is_required_to_create_a_purchase_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('purchase_orders.manage');
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency_id' => $currency->id,
        ], $headers)->assertForbidden();
    }
}
