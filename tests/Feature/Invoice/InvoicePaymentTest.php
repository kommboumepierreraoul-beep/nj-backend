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
 * Reglement rattache a un document precis (Doc/factures_modele_donnees.md, section 10,
 * ajout 2026-08-21 : "je veux le backend de reglement de factures"). Complete
 * SalesOrderPaymentTest/SalesOrderRefundAndFactureTest (paiement generique au niveau de
 * la commande, non modifie).
 */
class InvoicePaymentTest extends TestCase
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

    private function emitProforma(SalesOrder $salesOrder, array $headers): Invoice
    {
        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        return Invoice::query()->findOrFail($response->json('data.id'));
    }

    public function test_paying_a_proforma_directly_tags_the_payment_and_can_trigger_the_facture(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $response = $this->postJson("/api/invoices/{$proforma->id}/payments", [
            'amount' => 100000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ORANGE_MONEY',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.invoice_id', $proforma->id);

        $this->assertDatabaseHas('sales_order_payments', [
            'id' => $response->json('data.id'),
            'invoice_id' => $proforma->id,
            'sales_order_id' => $salesOrder->id,
        ]);

        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $salesOrder->id,
            'document_type' => InvoiceDocumentType::FACTURE->value,
        ]);
    }

    public function test_a_payment_cannot_be_attached_to_a_credit_note(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $avoirId = $this->postJson("/api/invoices/{$proforma->id}/credit-notes", [], $headers)
            ->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/{$avoirId}/payments", [
            'amount' => 1000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertStatus(422);
    }

    public function test_invoice_scoped_payments_are_listed_independently_from_the_order_wide_list(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $this->postJson("/api/invoices/{$proforma->id}/payments", [
            'amount' => 40000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        // Paiement generique, non rattache a un document precis : ne doit pas apparaitre
        // dans la liste filtree par document.
        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 60000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $scoped = $this->getJson("/api/invoices/{$proforma->id}/payments", $headers)->assertOk();
        $this->assertCount(1, $scoped->json('data'));
        $this->assertEquals(40000, (float) $scoped->json('data.0.amount'));

        $all = $this->getJson("/api/sales-orders/{$salesOrder->id}/payments", $headers)->assertOk();
        $this->assertCount(2, $all->json('data'));
    }

    // Garde-fou ajoute le 2026-08-25 (absent de la version initiale de storeForInvoice()) :
    // un document REMPLACEE (nouvelle version de proforma deja emise) n'est plus le document
    // actif de la commande, un paiement ne doit plus pouvoir s'y rattacher — meme regle que
    // CreditNoteController::store() pour un avoir (test_a_payment_cannot_be_attached_to_a_credit_note).
    public function test_a_payment_cannot_be_attached_to_a_replaced_proforma_version(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();
        $firstVersion = $this->emitProforma($salesOrder, $headers);

        // Reemission : cree la v2 et fait automatiquement passer la v1 a REMPLACEE.
        $this->emitProforma($salesOrder, $headers);
        $this->assertEquals('REMPLACEE', $firstVersion->fresh()->status->value);

        $this->postJson("/api/invoices/{$firstVersion->id}/payments", [
            'amount' => 1000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertStatus(422);
    }

    public function test_manage_payments_permission_is_required_for_an_invoice_scoped_payment(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('sales_orders.manage_payments');
        $salesOrder = $this->makeOrderWithSelectedItem();
        $proforma = $this->emitProforma($salesOrder, $headers);

        $this->postJson("/api/invoices/{$proforma->id}/payments", [
            'amount' => 1000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertForbidden();
    }
}
