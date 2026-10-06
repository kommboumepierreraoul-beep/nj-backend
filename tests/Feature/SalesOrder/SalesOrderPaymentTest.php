<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\SalesOrderPaymentStatus;
use App\Models\Currency;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SalesOrderPaymentTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_a_partial_payment_marks_the_order_as_partially_paid(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create(['total_amount' => 100000, 'currency_id' => $currency->id]);

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $currency->id,
            'payment_method' => 'ORANGE_MONEY',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals('REC-'.$salesOrder->reference, $response->json('data.receipt_number'));
        $this->assertEquals(SalesOrderPaymentStatus::PARTIELLEMENT_PAYEE->value, $salesOrder->fresh()->payment_status->value);
    }

    public function test_a_final_payment_marks_the_order_as_fully_paid_and_reconciles_the_total(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create(['total_amount' => 100000, 'currency_id' => $currency->id]);

        $first = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 40000,
            'currency_id' => $currency->id,
            'payment_method' => 'ORANGE_MONEY',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $second = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 60000,
            'currency_id' => $currency->id,
            'payment_method' => 'WAVE',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals('REC-'.$salesOrder->reference, $first->json('data.receipt_number'));
        $this->assertEquals('REC-'.$salesOrder->reference.'-2', $second->json('data.receipt_number'));

        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);
        $this->assertEquals(100000, (float) $salesOrder->payments()->where('is_voided', false)->sum('amount'));
    }

    public function test_voiding_the_only_payment_resets_the_order_to_unpaid(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create(['total_amount' => 100000, 'currency_id' => $currency->id]);

        $paymentId = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $currency->id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated()->json('data.id');

        $this->assertEquals(SalesOrderPaymentStatus::PAYEE->value, $salesOrder->fresh()->payment_status->value);

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments/{$paymentId}/void", [
            'voided_reason' => 'Erreur de saisie',
        ], $headers)->assertOk();

        $this->assertEquals(SalesOrderPaymentStatus::NON_PAYEE->value, $salesOrder->fresh()->payment_status->value);
        $this->assertDatabaseHas('sales_order_payments', ['id' => $paymentId, 'is_voided' => true]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'SalesOrder',
            'entity_id' => $salesOrder->id,
            'action' => 'sales_order.payment_voided',
        ]);
    }

    public function test_voiding_an_already_voided_payment_is_rejected(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create(['total_amount' => 100000, 'currency_id' => $currency->id]);

        $paymentId = $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 100000,
            'currency_id' => $currency->id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertCreated()->json('data.id');

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments/{$paymentId}/void", [
            'voided_reason' => 'Premiere annulation',
        ], $headers)->assertOk();

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments/{$paymentId}/void", [
            'voided_reason' => 'Deuxieme tentative',
        ], $headers)->assertStatus(422);
    }

    public function test_manage_payments_permission_is_required_to_record_a_payment(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('sales_orders.manage_payments');
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create(['currency_id' => $currency->id]);

        $this->postJson("/api/sales-orders/{$salesOrder->id}/payments", [
            'amount' => 1000,
            'currency_id' => $currency->id,
            'payment_method' => 'ESPECES',
            'paid_at' => now()->toDateString(),
        ], $headers)->assertForbidden();
    }
}
