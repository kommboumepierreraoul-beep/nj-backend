<?php

namespace Tests\Feature\Product;

use App\Enums\PriceSource;
use App\Models\Currency;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductPriceHistoryTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_record_and_list_a_price_history_entry(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson("/api/product-variants/{$variant->id}/price-history", [
            'price' => 9.9,
            'currency_id' => $currency->id,
            'source' => PriceSource::MANUAL->value,
            'effective_date' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('product_price_history', [
            'product_variant_id' => $variant->id,
            'recorded_by_user_id' => $admin->id,
        ]);

        $this->getJson("/api/product-variants/{$variant->id}/price-history", $headers)->assertOk();
    }
}
