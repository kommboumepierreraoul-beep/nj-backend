<?php

namespace Tests\Feature\SalesOrder;

use App\Enums\InvoiceDocumentType;
use App\Models\Client;
use App\Models\CommissionRule;
use App\Models\CompanySettings;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * TVA de bout en bout (Doc/tva_addendum.md) : taux unique sur la commande, base
 * « sous-total - remise + commission », report figé sur les documents émis.
 */
class SalesOrderTaxTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function currency(): Currency
    {
        return Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true],
        );
    }

    private function ensureCommissionRule(): void
    {
        CommissionRule::query()->firstOrCreate(
            ['min_amount' => 0],
            ['max_amount' => null, 'commission_type' => 'POURCENTAGE', 'rate_or_amount' => 10, 'is_active' => true, 'label' => 'Défaut'],
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => Client::factory()->create()->id,
            'type' => 'MULTI_PRODUITS',
            'currency_id' => $this->currency()->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_type' => 'SERVICE', 'label' => 'Sourcing', 'quantity' => 1, 'unit_price' => 200000],
            ],
        ], $overrides);
    }

    public function test_explicit_tax_rate_is_applied_to_subtotal_plus_commission(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();

        $response = $this->postJson('/api/sales-orders', $this->payload(['tax_rate' => 19.25]), $headers)
            ->assertCreated();

        // sous-total 200 000 + commission 10 % (20 000) = 220 000 ; TVA 19,25 % = 42 350 ;
        // total TTC = 262 350.
        $response->assertJsonPath('data.tax_rate', '19.25')
            ->assertJsonPath('data.tax_amount', '42350.00')
            ->assertJsonPath('data.total_amount', '262350.00');
    }

    public function test_missing_tax_rate_falls_back_to_company_default(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();
        CompanySettings::query()->firstOrFail()->update(['default_tax_rate' => 10]);

        $this->postJson('/api/sales-orders', $this->payload(), $headers)
            ->assertCreated()
            ->assertJsonPath('data.tax_rate', '10.00')
            ->assertJsonPath('data.tax_amount', '22000.00'); // 220 000 * 10 %
    }

    public function test_zero_tax_rate_stores_null_and_leaves_total_untouched(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();

        $this->postJson('/api/sales-orders', $this->payload(['tax_rate' => 0]), $headers)
            ->assertCreated()
            ->assertJsonPath('data.tax_rate', null)
            ->assertJsonPath('data.tax_amount', '0.00')
            ->assertJsonPath('data.total_amount', '220000.00');
    }

    public function test_updating_tax_rate_recomputes_amount_and_total(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();

        $id = $this->postJson('/api/sales-orders', $this->payload(), $headers)->assertCreated()->json('data.id');

        $this->putJson("/api/sales-orders/{$id}", ['tax_rate' => 20], $headers)
            ->assertOk()
            ->assertJsonPath('data.tax_amount', '44000.00')
            ->assertJsonPath('data.total_amount', '264000.00');
    }

    public function test_adding_an_item_keeps_the_tax_rate_and_recomputes_the_amount(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();

        $id = $this->postJson('/api/sales-orders', $this->payload(['tax_rate' => 10]), $headers)
            ->assertCreated()->json('data.id');

        $this->postJson("/api/sales-orders/{$id}/items", [
            'item_type' => 'SERVICE', 'label' => 'Extra', 'quantity' => 1, 'unit_price' => 100000,
        ], $headers)->assertCreated();

        $order = SalesOrder::query()->findOrFail($id);
        // sous-total 300 000 + commission figée 20 000 = 320 000 ; TVA 10 % = 32 000.
        $this->assertSame('32000.00', (string) $order->tax_amount);
        $this->assertSame('352000.00', (string) $order->total_amount);
    }

    public function test_emitted_proforma_carries_a_frozen_copy_of_the_tax(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $this->ensureCommissionRule();

        $id = $this->postJson('/api/sales-orders', $this->payload(['tax_rate' => 18]), $headers)
            ->assertCreated()->json('data.id');

        $this->postJson("/api/sales-orders/{$id}/proforma", [], $headers)->assertCreated();

        $proforma = Invoice::query()->where('sales_order_id', $id)
            ->where('document_type', InvoiceDocumentType::PROFORMA->value)->firstOrFail();

        $this->assertSame('18.00', (string) $proforma->tax_rate);
        $this->assertSame('39600.00', (string) $proforma->tax_amount); // 220 000 * 18 %
        $this->assertSame('259600.00', (string) $proforma->total_amount);
    }
}
