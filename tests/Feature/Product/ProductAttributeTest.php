<?php

namespace Tests\Feature\Product;

use App\Enums\AttributeInputType;
use App\Models\ProductAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductAttributeTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_an_attribute(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/product-attributes', [
            'name' => 'Couleur',
            'code' => 'couleur',
            'input_type' => AttributeInputType::SELECT->value,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('product_attributes', ['code' => 'couleur']);
    }

    public function test_admin_can_add_a_value_to_an_attribute(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Couleur',
            'code' => 'couleur',
            'input_type' => AttributeInputType::SELECT->value,
        ]);

        $this->postJson("/api/product-attributes/{$attribute->id}/values", [
            'value' => 'Rouge',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_attribute_id' => $attribute->id,
            'value' => 'Rouge',
        ]);
    }

    public function test_admin_can_list_update_and_delete_an_attribute(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Taille',
            'code' => 'taille',
            'input_type' => AttributeInputType::TEXT->value,
        ]);

        $this->getJson('/api/product-attributes', $headers)->assertOk();

        $this->putJson("/api/product-attributes/{$attribute->id}", [
            'name' => 'Taille (cm)',
        ], $headers)->assertOk()->assertJsonPath('data.name', 'Taille (cm)');

        $this->deleteJson("/api/product-attributes/{$attribute->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('product_attributes', ['id' => $attribute->id]);
    }
}
