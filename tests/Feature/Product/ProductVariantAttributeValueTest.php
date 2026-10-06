<?php

namespace Tests\Feature\Product;

use App\Enums\AttributeInputType;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductVariantAttributeValueTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_assign_a_custom_attribute_value_to_a_variant(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Matiere',
            'code' => 'matiere',
            'input_type' => AttributeInputType::TEXT->value,
        ]);

        $this->postJson("/api/product-variants/{$variant->id}/attribute-values", [
            'product_attribute_id' => $attribute->id,
            'custom_value' => 'Inox 304',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('product_variant_attribute_values', [
            'product_variant_id' => $variant->id,
            'product_attribute_id' => $attribute->id,
            'custom_value' => 'Inox 304',
        ]);
    }

    public function test_admin_can_list_and_remove_a_variant_attribute_value(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $variant = ProductVariant::factory()->create();
        $attribute = ProductAttribute::query()->create([
            'name' => 'Matiere',
            'code' => 'matiere-2',
            'input_type' => AttributeInputType::TEXT->value,
        ]);
        $assignment = $variant->attributeValues()->create([
            'product_attribute_id' => $attribute->id,
            'custom_value' => 'Plastique',
        ]);

        $this->getJson("/api/product-variants/{$variant->id}/attribute-values", $headers)->assertOk();

        $this->deleteJson("/api/product-variants/{$variant->id}/attribute-values/{$assignment->id}", [], $headers)
            ->assertOk();

        $this->assertDatabaseMissing('product_variant_attribute_values', ['id' => $assignment->id]);
    }
}
