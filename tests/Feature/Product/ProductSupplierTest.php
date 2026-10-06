<?php

namespace Tests\Feature\Product;

use App\Models\Currency;
use App\Models\ProductSupplier;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductSupplierTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_link_a_supplier_to_a_variant(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson("/api/product-variants/{$variant->id}/suppliers", [
            'supplier_id' => $supplier->id,
            'unit_price' => 4.5,
            'currency_id' => $currency->id,
            'moq' => 200,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('product_supplier', [
            'product_variant_id' => $variant->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_listing_suppliers_of_a_variant_eager_loads_supplier_and_currency(): void
    {
        // Couvre la regression corrigee dans ProductSupplierController::index()
        // (l'ancienne version tentait d'eager-loader "currency" sur le modele
        // Supplier, qui n'a pas cette relation, et aurait plante l'appel).
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();
        ProductSupplier::query()->create([
            'product_variant_id' => $variant->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 3,
            'currency_id' => $currency->id,
        ]);

        $this->getJson("/api/product-variants/{$variant->id}/suppliers", $headers)
            ->assertOk()
            ->assertJsonPath('data.0.supplier.id', $supplier->id)
            ->assertJsonPath('data.0.currency.id', $currency->id);
    }

    public function test_setting_a_link_as_preferred_unsets_the_previous_preferred_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $currency = Currency::factory()->create();
        $firstLink = ProductSupplier::query()->create([
            'product_variant_id' => $variant->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'unit_price' => 3,
            'currency_id' => $currency->id,
            'is_preferred' => true,
        ]);
        $secondSupplier = Supplier::factory()->create();

        $created = $this->postJson("/api/product-variants/{$variant->id}/suppliers", [
            'supplier_id' => $secondSupplier->id,
            'unit_price' => 2.8,
            'currency_id' => $currency->id,
            'is_preferred' => true,
        ], $headers)->json('data.id');

        $this->assertDatabaseHas('product_supplier', ['id' => $created, 'is_preferred' => true]);
        $this->assertDatabaseHas('product_supplier', ['id' => $firstLink->id, 'is_preferred' => false]);
    }

    public function test_admin_can_delete_a_product_supplier_link(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $link = ProductSupplier::query()->create([
            'product_variant_id' => $variant->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'unit_price' => 1,
            'currency_id' => Currency::factory()->create()->id,
        ]);

        $this->deleteJson("/api/product-variants/{$variant->id}/suppliers/{$link->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('product_supplier', ['id' => $link->id]);
    }
}
