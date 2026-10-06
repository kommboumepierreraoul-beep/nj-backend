<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\InvoiceDocumentType;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Currency;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Couvre les extensions du 2026-08-18 (Doc/factures_modele_donnees.md, section 9) :
 * remboursements (direction=REMBOURSEMENT) et emission automatique de la FACTURE au
 * passage a PAYEE. Complete SalesOrderPaymentTest (scenarios d'encaissement simple,
 * non modifies et toujours verts avec cette extension).
 */
class SalesOrderRefundAndFactureTest extends TestCase
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

    public function test_a_refund_reduces_the_net_paid_amount_and_can_revert_payment_status(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $refund = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 50000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'direction' => 'REMBOURSEMENT',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $refund->assertJsonPath('data.direction', 'REMBOURSEMENT')
            ->assertJsonPath('data.receipt_number', 'REMB-'.$salesOrder->reference);

        $this->assertEquals(SalesOrderPaymentStatus::PARTIELLEMENT_PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'SalesOrder',
            'entity_id' => $salesOrder->id,
            'action' => 'sales_order.refund_recorded',
        ]);
    }

    public function test_a_refund_exceeding_the_net_paid_amount_is_rejected(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 50000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'direction' => 'REMBOURSEMENT',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertStatus(422);
    }

    public function test_full_payment_auto_issues_a_facture_with_attachment_and_audit_log(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ORANGE_MONEY',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $salesOrder->id,
            'document_type' => InvoiceDocumentType::FACTURE->value,
            'invoice_number' => 'FACT-'.$salesOrder->reference,
        ]);

        $invoiceId = \App\Models\Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('document_type', InvoiceDocumentType::FACTURE->value)
            ->value('id');

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => \App\Models\Invoice::class,
            'attachable_id' => $invoiceId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Invoice',
            'entity_id' => $invoiceId,
            'action' => 'invoice.issued',
        ]);
    }

    public function test_a_subsequent_payment_while_already_fully_paid_does_not_reissue_a_facture(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 60000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 1000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals(1, \App\Models\Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('document_type', InvoiceDocumentType::FACTURE->value)
            ->count());
    }

    public function test_facture_auto_emission_is_skipped_when_no_item_is_selected(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();

        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]
        );
        $salesOrder = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::BROUILLON->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);
        $salesOrder->items()->create([
            'item_type' => 'SERVICE',
            'label' => 'Option non tranchee',
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => 0,
            'subtotal' => 100000,
            'is_selected' => false,
            'sort_order' => 0,
        ]);

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);
        $this->assertDatabaseMissing('invoices', [
            'sales_order_id' => $salesOrder->id,
            'document_type' => InvoiceDocumentType::FACTURE->value,
        ]);
    }
}
