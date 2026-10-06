<?php

namespace Tests\Feature\Product;

use App\Enums\VariantLevel;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_variant_for_a_product(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'SKU-0001',
            'name' => 'Premier choix',
            'level' => VariantLevel::PREMIER_CHOIX->value,
            'purchase_price' => 12.5,
            'purchase_currency_id' => $currency->id,
        ], $headers)->assertCreated()->assertJsonPath('data.sku', 'SKU-0001');

        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU-0001', 'product_id' => $product->id]);
    }

    public function test_setting_a_variant_as_default_unsets_the_previous_default(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $firstVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true]);
        $secondVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);

        $this->putJson("/api/products/{$product->id}/variants/{$secondVariant->id}", [
            'is_default' => true,
        ], $headers)->assertOk();

        $this->assertDatabaseHas('product_variants', ['id' => $secondVariant->id, 'is_default' => true]);
        $this->assertDatabaseHas('product_variants', ['id' => $firstVariant->id, 'is_default' => false]);
    }

    public function test_admin_can_list_and_show_variants_of_a_product(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->getJson("/api/products/{$product->id}/variants", $headers)->assertOk();

        $this->getJson("/api/products/{$product->id}/variants/{$variant->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $variant->id);
    }

    public function test_admin_can_delete_a_variant(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->deleteJson("/api/products/{$product->id}/variants/{$variant->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }
}
