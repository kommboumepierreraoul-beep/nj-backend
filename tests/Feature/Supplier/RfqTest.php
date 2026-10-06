<?php

namespace Tests\Feature\Supplier;

use App\Enums\RFQStatus;
use App\Models\Currency;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_an_rfq_with_an_auto_generated_reference(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/rfqs', [
            'request_date' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertNotEmpty($response->json('data.reference'));
        $this->assertDatabaseHas('rfqs', ['requested_by_user_id' => $admin->id]);
    }

    public function test_admin_can_show_an_rfq_with_its_items_and_suppliers(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        RfqItem::factory()->create(['rfq_id' => $rfq->id]);

        $this->getJson("/api/rfqs/{$rfq->id}", $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'rfq_suppliers']]);
    }

    public function test_admin_can_add_an_item_to_an_rfq(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();

        $this->postJson("/api/rfqs/{$rfq->id}/items", [
            'custom_description' => 'Casseroles inox 24cm',
            'target_quantity' => 500,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('rfq_items', ['rfq_id' => $rfq->id, 'target_quantity' => 500]);
    }

    public function test_an_item_without_a_product_requires_a_custom_description(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();

        $this->postJson("/api/rfqs/{$rfq->id}/items", [
            'target_quantity' => 100,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['custom_description']);
    }

    public function test_admin_can_attach_a_supplier_to_an_rfq(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers", [
            'supplier_id' => $supplier->id,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('rfq_suppliers', ['rfq_id' => $rfq->id, 'supplier_id' => $supplier->id]);
    }

    public function test_a_supplier_cannot_be_attached_twice_to_the_same_rfq(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        $supplier = Supplier::factory()->create();
        $rfq->rfqSuppliers()->create(['supplier_id' => $supplier->id]);

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers", [
            'supplier_id' => $supplier->id,
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_record_a_supplier_quote_and_select_it(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        $item = RfqItem::factory()->create(['rfq_id' => $rfq->id]);
        $supplier = Supplier::factory()->create();
        $rfqSupplier = $rfq->rfqSuppliers()->create(['supplier_id' => $supplier->id]);
        $currency = Currency::factory()->create();

        $firstQuoteId = $this->postJson("/api/rfq-suppliers/{$rfqSupplier->id}/quotes", [
            'rfq_item_id' => $item->id,
            'quoted_unit_price' => 3.2,
            'currency_id' => $currency->id,
            'quoted_at' => now()->toDateTimeString(),
        ], $headers)->assertCreated()->json('data.id');

        $secondSupplier = Supplier::factory()->create();
        $secondRfqSupplier = $rfq->rfqSuppliers()->create(['supplier_id' => $secondSupplier->id]);
        $secondQuoteId = $this->postJson("/api/rfq-suppliers/{$secondRfqSupplier->id}/quotes", [
            'rfq_item_id' => $item->id,
            'quoted_unit_price' => 2.9,
            'currency_id' => $currency->id,
            'quoted_at' => now()->toDateTimeString(),
        ], $headers)->json('data.id');

        $this->postJson("/api/rfq-suppliers/{$secondRfqSupplier->id}/quotes/{$secondQuoteId}/select", [], $headers)
            ->assertOk();

        $this->assertDatabaseHas('rfq_supplier_quotes', ['id' => $secondQuoteId, 'is_selected' => true]);
        $this->assertDatabaseHas('rfq_supplier_quotes', ['id' => $firstQuoteId, 'is_selected' => false]);
    }

    public function test_admin_can_update_rfq_status(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create(['status' => RFQStatus::BROUILLON->value]);

        $this->putJson("/api/rfqs/{$rfq->id}", [
            'status' => RFQStatus::ENVOYE->value,
        ], $headers)->assertOk()->assertJsonPath('data.status', RFQStatus::ENVOYE->value);
    }

    public function test_admin_can_delete_an_rfq(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();

        $this->deleteJson("/api/rfqs/{$rfq->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('rfqs', ['id' => $rfq->id]);
    }
}
