<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Émission automatique du RECU tamponné « PAYÉ » au passage à PAYEE
 * (cahier des charges NJ Global Trade v2, §2.2 ; Doc/factures_recu_addendum.md).
 * Le RECU est une pièce distincte de la FACTURE définitive — les deux coexistent.
 */
class RecuGenerationTest extends TestCase
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

    public function test_paying_an_order_in_full_auto_issues_a_recu_alongside_the_facture(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeOrderWithSelectedItem();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ORANGE_MONEY',
            'external_reference' => 'OM-REF-9931',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertSame(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $recu = Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('document_type', InvoiceDocumentType::RECU->value)
            ->first();

        $this->assertNotNull($recu, 'Un RECU aurait dû être émis au passage à PAYEE.');
        $this->assertSame('REC-'.$salesOrder->reference, $recu->invoice_number);
        $this->assertSame(InvoiceStatus::EMISE->value, $recu->status->value);
        $this->assertSame(1, $recu->items()->count());
        $this->assertSame('Sourcing fournisseur', $recu->items()->first()->label);

        // PDF attaché.
        $attachment = $recu->attachments()->first();
        $this->assertNotNull($attachment);
        Storage::disk('public')->assertExists($attachment->file_path);

        // Le mouvement d'encaissement déclencheur pointe vers le RECU qu'il a produit.
        $payment = SalesOrderPayment::query()->where('sales_order_id', $salesOrder->id)->latest('id')->first();
        $this->assertSame($recu->id, $payment->invoice_id);
        $this->assertSame('REC-'.$salesOrder->reference, $payment->receipt_number);

        // La FACTURE définitive existe toujours, indépendamment du RECU.
        $this->assertTrue(
            Invoice::query()->where('sales_order_id', $salesOrder->id)
                ->where('document_type', InvoiceDocumentType::FACTURE->value)->exists(),
        );
    }

    public function test_the_recu_appears_in_the_document_registry_and_is_filterable(): void
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

        $this->getJson('/api/invoices?document_type=RECU', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'REC-'.$salesOrder->reference)
            ->assertJsonPath('data.0.document_type', 'RECU');
    }

    public function test_a_second_full_settlement_does_not_create_a_second_recu(): void
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

        // Remboursement partiel puis nouvel encaissement : la commande repasse par PAYEE.
        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'direction' => 'REMBOURSEMENT',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertSame(
            1,
            Invoice::query()->where('sales_order_id', $salesOrder->id)
                ->where('document_type', InvoiceDocumentType::RECU->value)->count(),
        );
    }

    public function test_a_recu_rejects_attached_payments_and_whatsapp_send(): void
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

        $recu = Invoice::query()->where('sales_order_id', $salesOrder->id)
            ->where('document_type', InvoiceDocumentType::RECU->value)->firstOrFail();

        $this->postJson("/api/invoices/{$recu->id}/payments", [
            'amount' => 1000,
            'currency_id' => $salesOrder->currency_id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertStatus(422);

        $this->postJson("/api/invoices/{$recu->id}/send-whatsapp", [], $headers)->assertStatus(422);
    }
}
