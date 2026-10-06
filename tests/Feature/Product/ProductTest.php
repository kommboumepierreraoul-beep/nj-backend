<?php

namespace Tests\Feature\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_admin_can_create_a_product(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'reference' => 'REF-0001',
            'name' => 'Casserole en inox',
            'slug' => 'casserole-en-inox',
            'status' => ProductStatus::ACTIVE->value,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('products', [
            'reference' => 'REF-0001',
            'created_by_user_id' => $admin->id,
        ]);
        $response->assertJsonPath('data.category.id', $category->id);
    }

    public function test_a_sensitive_product_requires_a_sensitivity_reason(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();

        $this->postJson('/api/products', [
            'category_id' => $category->id,
            'reference' => 'REF-SENS',
            'name' => 'Produit sensible',
            'slug' => 'produit-sensible',
            'status' => ProductStatus::ACTIVE->value,
            'is_sensitive' => true,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['sensitivity_reason']);
    }

    public function test_admin_can_show_a_product_with_its_relations(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonStructure(['data' => ['category', 'variants', 'tags']]);
    }

    public function test_admin_can_update_a_product(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create(['name' => 'Ancien nom']);

        $this->putJson("/api/products/{$product->id}", [
            'name' => 'Nouveau nom produit',
        ], $headers)->assertOk()->assertJsonPath('data.name', 'Nouveau nom produit');
    }

    public function test_admin_can_delete_a_product_which_is_soft_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}", [], $headers)->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_sync_tags_on_a_product(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $tagA = Tag::query()->create(['name' => 'Promo', 'slug' => 'promo']);
        $tagB = Tag::query()->create(['name' => 'Nouveaute', 'slug' => 'nouveaute']);

        $this->putJson("/api/products/{$product->id}/tags", [
            'tag_ids' => [$tagA->id, $tagB->id],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('product_tag', ['product_id' => $product->id, 'tag_id' => $tagA->id]);
        $this->assertDatabaseHas('product_tag', ['product_id' => $product->id, 'tag_id' => $tagB->id]);
    }

    public function test_view_permission_is_required_to_list_products(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('products.view');

        $this->getJson('/api/products', $headers)->assertForbidden();
    }
}
