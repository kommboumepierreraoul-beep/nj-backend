<?php

namespace Tests\Feature\Product;

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_categories(): void
    {
        $this->getJson('/api/product-categories')->assertUnauthorized();
    }

    public function test_admin_can_create_a_category(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/product-categories', [
            'name' => 'Electromenager',
            'slug' => 'electromenager',
            'is_active' => true,
        ], $headers)->assertCreated()
            ->assertJsonPath('data.slug', 'electromenager');

        $this->assertDatabaseHas('product_categories', ['slug' => 'electromenager']);
    }

    public function test_creating_a_category_validates_required_fields(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/product-categories', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_admin_can_list_and_show_categories(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();

        $this->getJson('/api/product-categories', $headers)->assertOk();

        $this->getJson("/api/product-categories/{$category->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_admin_can_update_a_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create(['name' => 'Ancien nom']);

        $this->putJson("/api/product-categories/{$category->id}", [
            'name' => 'Nouveau nom',
        ], $headers)->assertOk()->assertJsonPath('data.name', 'Nouveau nom');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'name' => 'Nouveau nom']);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();

        $this->putJson("/api/product-categories/{$category->id}", [
            'parent_id' => $category->id,
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_delete_an_empty_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();

        $this->deleteJson("/api/product-categories/{$category->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->create(['parent_id' => $parent->id]);

        $this->deleteJson("/api/product-categories/{$parent->id}", [], $headers)->assertStatus(409);

        $this->assertDatabaseHas('product_categories', ['id' => $parent->id]);
    }

    public function test_manage_permission_is_required_to_create_a_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('products.manage');

        $this->postJson('/api/product-categories', [
            'name' => 'Bloque',
            'slug' => 'bloque',
        ], $headers)->assertForbidden();
    }
}
