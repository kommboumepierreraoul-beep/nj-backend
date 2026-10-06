<?php

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\ClientCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ClientCategoryTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_client_categories(): void
    {
        $this->getJson('/api/client-categories')->assertUnauthorized();
    }

    public function test_admin_can_create_a_client_category(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/client-categories', [
            'code' => 'ECOM_RICH',
            'label' => 'Ecom-Rich',
            'badge_color' => '#F5C518',
        ], $headers)->assertCreated()
            ->assertJsonPath('data.code', 'ECOM_RICH');

        $this->assertDatabaseHas('client_categories', ['code' => 'ECOM_RICH']);
    }

    public function test_creating_a_client_category_validates_required_fields(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/client-categories', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'label']);
    }

    public function test_client_category_code_must_be_unique(): void
    {
        [, $headers] = $this->actingAsAdmin();
        ClientCategory::query()->create(['code' => 'DIRECT', 'label' => 'Client direct']);

        $this->postJson('/api/client-categories', [
            'code' => 'DIRECT',
            'label' => 'Doublon',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_list_and_show_client_categories(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ClientCategory::query()->create(['code' => 'FACEBOOK', 'label' => 'Facebook']);

        $this->getJson('/api/client-categories', $headers)->assertOk();

        $this->getJson("/api/client-categories/{$category->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_admin_can_update_a_client_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ClientCategory::query()->create(['code' => 'AUTRE', 'label' => 'Ancien libelle']);

        $this->putJson("/api/client-categories/{$category->id}", [
            'label' => 'Nouveau libelle',
        ], $headers)->assertOk()->assertJsonPath('data.label', 'Nouveau libelle');

        $this->assertDatabaseHas('client_categories', ['id' => $category->id, 'label' => 'Nouveau libelle']);
    }

    public function test_admin_can_delete_an_unused_client_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ClientCategory::query()->create(['code' => 'TEMP', 'label' => 'Temporaire']);

        $this->deleteJson("/api/client-categories/{$category->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('client_categories', ['id' => $category->id]);
    }

    public function test_a_client_category_used_by_clients_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ClientCategory::query()->create(['code' => 'ECOM_RICH', 'label' => 'Ecom-Rich']);
        Client::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/client-categories/{$category->id}", [], $headers)->assertStatus(409);

        $this->assertDatabaseHas('client_categories', ['id' => $category->id]);
    }

    public function test_manage_permission_is_required_to_create_a_client_category(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.manage');

        $this->postJson('/api/client-categories', [
            'code' => 'BLOQUE',
            'label' => 'Bloque',
        ], $headers)->assertForbidden();
    }
}
