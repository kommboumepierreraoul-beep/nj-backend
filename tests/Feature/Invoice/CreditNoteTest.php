<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Emission d'un AVOIR (Doc/factures_modele_donnees.md, section 8), sortie du perimetre
 * "hors iteration" note dans 2026_08_17_000005_seed_invoice_and_company_permissions_table
 * le 2026-08-18.
 */
class CreditNoteTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeOrderWithTwoUnitItem(array $orderAttributes = []): SalesOrder
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
            'item_type' => 'PRODUIT',
            'label' => 'Lot de pieces',
            'quantity' => 2,
            'unit_price' => 50000,
            'discount_amount' => 0,
            'subtotal' => 100000,
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        return $salesOrder;
    }

    private function emitProforma(SalesOrder $salesOrder, array $headers): Invoice
    {
        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        return Invoice::query()->findOrFail($response->json('data.id'));
    }

    public function test_credit_note_defaults_to_a_full_credit_and_marks_the_order_as_paid(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithTwoUnitItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $response = $this->postJson("/api/invoices/{$proforma->id}/credit-notes", [], $headers)->assertCreated();

        $response->assertJsonPath('data.document_type', InvoiceDocumentType::AVOIR->value)
            ->assertJsonPath('data.credits_invoice_id', $proforma->id)
            ->assertJsonPath('data.total_amount', '100000.00');

        $this->assertStringStartsWith('AVR-'.$salesOrder->reference, $response->json('data.invoice_number'));

        $this->assertEquals('100000.00', $salesOrder->fresh()->credited_amount);
        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Invoice',
            'entity_id' => $response->json('data.id'),
            'action' => 'invoice.credit_note_issued',
        ]);
    }

    public function test_credit_note_can_target_a_partial_quantity(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithTwoUnitItem();
        $proforma = $this->emitProforma($salesOrder, $headers);
        $lineId = $proforma->items()->first()->id;

        $this->postJson("/api/invoices/{$proforma->id}/credit-notes", [
            'items' => [
                ['invoice_item_id' => $lineId, 'quantity' => 1],
            ],
        ], $headers)->assertCreated()
            ->assertJsonPath('data.total_amount', '50000.00');

        $this->assertEquals('50000.00', $salesOrder->fresh()->credited_amount);
        // Moitie creditee seulement, aucun encaissement : la commande reste non payee.
        $this->assertEquals(SalesOrderPaymentStatus::NON_PAYEE->value, $salesOrder->fresh()->payment_status->value);
    }

    public function test_credit_note_cannot_target_another_credit_note(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithTwoUnitItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $avoirId = $this->postJson("/api/invoices/{$proforma->id}/credit-notes", [], $headers)
            ->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/{$avoirId}/credit-notes", [], $headers)->assertStatus(422);
    }

    public function test_credit_note_requires_the_manage_credit_notes_permission(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.manage_credit_notes');
        $salesOrder = $this->makeOrderWithTwoUnitItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $this->postJson("/api/invoices/{$proforma->id}/credit-notes", [], $headers)->assertForbidden();
    }
}
