<?php

namespace Tests\Feature\Company;

use App\Models\Currency;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Ecran "Parametres -> Entreprise -> Devises"
 * (App\Http\Controllers\Company\CurrencyController) — volet ecriture. La lecture
 * (GET /api/currencies) reste celle de ReferenceData et n'est pas retestee ici.
 */
class CurrencyManagementTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_currency_and_the_code_is_uppercased(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/currencies', [
            'code' => 'sgd', 'name' => 'Dollar de Singapour', 'symbol' => 'S$',
        ], $headers)->assertCreated()->assertJsonPath('data.code', 'SGD');

        $this->assertDatabaseHas('currencies', ['code' => 'SGD', 'name' => 'Dollar de Singapour']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'currency.created', 'entity_type' => 'Currency']);
    }

    public function test_setting_a_currency_as_default_unsets_the_previous_default(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $previous = Currency::factory()->create(['code' => 'XAF', 'is_default' => true]);

        $new = Currency::factory()->create(['code' => 'USD', 'is_default' => false]);
        $this->putJson("/api/currencies/{$new->id}", ['is_default' => true], $headers)->assertOk();

        $this->assertTrue($new->fresh()->is_default);
        $this->assertFalse($previous->fresh()->is_default);
    }

    public function test_the_default_status_cannot_be_removed_directly(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_default' => true]);

        $this->putJson("/api/currencies/{$currency->id}", ['is_default' => false], $headers)->assertStatus(422);
        $this->assertTrue($currency->fresh()->is_default);
    }

    public function test_a_currency_referenced_by_a_product_variant_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_default' => false]);
        ProductVariant::factory()->create(['purchase_currency_id' => $currency->id]);

        $this->deleteJson("/api/currencies/{$currency->id}", [], $headers)->assertStatus(409);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_the_default_currency_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_default' => true]);

        $this->deleteJson("/api/currencies/{$currency->id}", [], $headers)->assertStatus(422);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_a_referenced_currency_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_default' => false]);
        SalesOrder::factory()->create(['currency_id' => $currency->id]);

        $this->deleteJson("/api/currencies/{$currency->id}", [], $headers)->assertStatus(409);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_an_unreferenced_non_default_currency_can_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['is_default' => false]);

        $this->deleteJson("/api/currencies/{$currency->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'currency.deleted']);
    }

    public function test_admin_can_append_an_exchange_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'CNY']);

        $this->postJson("/api/currencies/{$currency->id}/exchange-rates", [
            'rate_to_xaf' => 82.5,
            'effective_date' => '2026-09-01',
        ], $headers)->assertCreated()->assertJsonPath('data.latest_rate_to_xaf', '82.500000');

        $this->assertDatabaseHas('exchange_rate_history', [
            'currency_id' => $currency->id,
            'rate_to_xaf' => 82.5,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'currency.exchange_rate_added']);
    }

    public function test_a_non_positive_exchange_rate_is_rejected(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();

        $this->postJson("/api/currencies/{$currency->id}/exchange-rates", [
            'rate_to_xaf' => 0, 'effective_date' => '2026-09-01',
        ], $headers)->assertStatus(422);
    }

    public function test_manage_permission_is_required(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('company_settings.manage');

        $this->postJson('/api/currencies', ['code' => 'AAA', 'name' => 'Nope'], $headers)->assertForbidden();
    }
}
