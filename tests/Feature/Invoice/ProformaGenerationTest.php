<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProformaGenerationTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeOrderWithSelectedItem(array $orderAttributes = []): SalesOrder
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]
        );

        $salesOrder = SalesOrder::factory()->create(array_merge([
            'status' => SalesOrderStatus::BROUILLON->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ], $orderAttributes));

        $salesOrder->items()->create([
            'item_type' => 'SERVICE',
            'label' => 'Sourcing fournisseur',
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => 0,
            'subtotal' => 100000,
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        return $salesOrder;
    }

    public function test_emitting_v1_does_not_change_order_status_creates_attachment_and_audit_log(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)
            ->assertCreated();

        $response->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.document_type', InvoiceDocumentType::PROFORMA->value)
            ->assertJsonPath('data.status', InvoiceStatus::EMISE->value)
            ->assertJsonPath('data.invoice_number', $salesOrder->reference);

        // Decision revue le 2026-08-17 : emettre une proforma est decouple du statut de la
        // commande, qui reste inchange (BROUILLON) jusqu'a un changement explicite via
        // SalesOrderController::updateStatus().
        $this->assertEquals(SalesOrderStatus::BROUILLON, $salesOrder->fresh()->status);

        $this->assertDatabaseMissing('sales_order_status_history', [
            'sales_order_id' => $salesOrder->id,
        ]);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => \App\Models\Invoice::class,
            'attachable_id' => $response->json('data.id'),
        ]);

        $this->assertDatabaseHas('attachment_media_types', [
            'type' => 'INVOICE_DOCUMENT',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Invoice',
            'entity_id' => $response->json('data.id'),
            'action' => 'invoice.issued',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $response->json('data.id'),
            'label' => 'Sourcing fournisseur',
            'subtotal' => 100000,
        ]);
    }

    public function test_reemitting_creates_a_v2_that_supersedes_v1(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $v1 = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();
        $this->assertEquals(SalesOrderStatus::BROUILLON, $salesOrder->fresh()->status);

        $v2 = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        $v2->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.invoice_number', $salesOrder->reference.'-V2')
            ->assertJsonPath('data.supersedes_invoice_id', $v1->json('data.id'));

        $this->assertDatabaseHas('invoices', [
            'id' => $v1->json('data.id'),
            'status' => InvoiceStatus::REMPLACEE->value,
        ]);

        // Emettre une proforma ne touche jamais au statut de la commande, quelle que soit
        // la version.
        $this->assertEquals(SalesOrderStatus::BROUILLON, $salesOrder->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Invoice',
            'entity_id' => $v1->json('data.id'),
            'action' => 'invoice.superseded',
        ]);
    }

    public function test_rejects_when_no_item_is_selected(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'XAF']);
        $salesOrder = SalesOrder::factory()->create(['status' => SalesOrderStatus::BROUILLON->value, 'currency_id' => $currency->id]);

        $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertStatus(422);
    }

    public function test_rejects_when_order_is_cancelled_or_closed(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $cancelled = $this->makeOrderWithSelectedItem(['status' => SalesOrderStatus::ANNULEE->value]);
        $this->postJson("/api/sales-orders/{$cancelled->id}/proforma", [], $headers)->assertStatus(422);

        $closed = $this->makeOrderWithSelectedItem(['status' => SalesOrderStatus::CLOTUREE->value]);
        $this->postJson("/api/sales-orders/{$closed->id}/proforma", [], $headers)->assertStatus(422);
    }

    public function test_manage_permission_is_required_to_emit_a_proforma(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.manage');
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertForbidden();
    }

    public function test_view_permission_is_required_to_list_proformas(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.view');
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->getJson("/api/sales-orders/{$salesOrder->id}/proformas", $headers)->assertForbidden();
    }

    public function test_currency_equivalents_are_populated_when_a_rate_exists_for_another_active_currency(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $usd = Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        ExchangeRateHistory::query()->create([
            'currency_id' => $usd->id,
            'rate_to_xaf' => 610,
            'effective_date' => now()->toDateString(),
        ]);

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        $equivalents = collect($response->json('data.currency_equivalents'));
        $this->assertTrue($equivalents->contains(fn ($equivalent) => $equivalent['code'] === 'USD'));

        $usdEquivalent = $equivalents->firstWhere('code', 'USD');
        $this->assertEquals(round(100000 / 610, 2), (float) $usdEquivalent['amount']);
    }

    public function test_currency_equivalents_are_empty_when_no_rate_is_available(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        $this->assertEmpty($response->json('data.currency_equivalents'));
    }
}
