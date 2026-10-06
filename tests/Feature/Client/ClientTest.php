<?php

namespace Tests\Feature\Client;

use App\Enums\ClientStatus;
use App\Enums\ValueSegment;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_clients(): void
    {
        $this->getJson('/api/clients')->assertUnauthorized();
    }

    public function test_admin_can_create_a_client(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/clients', [
            'full_name' => 'Jean Mbarga',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('clients', [
            'full_name' => 'Jean Mbarga',
            'created_by_user_id' => $admin->id,
            'client_type' => 'PARTICULIER',
            'billing_mode' => 'COMMISSION_VISIBLE',
            'status' => 'ACTIF',
        ]);
        $response->assertJsonPath('data.full_name', 'Jean Mbarga');
    }

    public function test_creating_a_client_validates_required_fields(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/clients', [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name']);
    }

    public function test_a_client_can_be_created_without_a_category(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/clients', [
            'full_name' => 'Client sans categorie',
        ], $headers)->assertCreated()->assertJsonPath('data.category_id', null);
    }

    public function test_custom_commission_rate_is_required_when_has_custom_commission_is_enabled(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/clients', [
            'full_name' => 'Client commission',
            'has_custom_commission' => true,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['custom_commission_rate']);
    }

    public function test_admin_can_show_a_client_with_its_relations(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $category = ClientCategory::query()->create(['code' => 'ECOM_RICH', 'label' => 'Ecom-Rich']);
        $client = Client::factory()->create(['category_id' => $category->id]);

        $this->getJson("/api/clients/{$client->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonStructure(['data' => ['category', 'contacts', 'tags']]);
    }

    public function test_admin_can_update_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create(['full_name' => 'Ancien nom']);

        $this->putJson("/api/clients/{$client->id}", [
            'full_name' => 'Nouveau nom client',
        ], $headers)->assertOk()->assertJsonPath('data.full_name', 'Nouveau nom client');
    }

    public function test_a_client_cannot_be_its_own_referrer(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}", [
            'referred_by_client_id' => $client->id,
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_delete_a_client_which_is_soft_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->deleteJson("/api/clients/{$client->id}", [], $headers)->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_admin_can_update_the_status_of_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}/status", [
            'status' => ClientStatus::BLOQUE->value,
        ], $headers)->assertOk()->assertJsonPath('data.status', ClientStatus::BLOQUE->value);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'status' => ClientStatus::BLOQUE->value]);
    }

    public function test_updating_the_status_of_a_client_requires_a_valid_enum_value(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}/status", [
            'status' => 'INCONNU',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_update_the_value_segment_of_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}/value-segment", [
            'value_segment' => ValueSegment::PLATINE->value,
        ], $headers)->assertOk()->assertJsonPath('data.value_segment', ValueSegment::PLATINE->value);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'value_segment' => ValueSegment::PLATINE->value]);
    }

    public function test_admin_can_sync_tags_on_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $tagA = Tag::query()->create(['name' => 'VIP', 'slug' => 'vip']);
        $tagB = Tag::query()->create(['name' => 'Grossiste', 'slug' => 'grossiste']);

        $this->putJson("/api/clients/{$client->id}/tags", [
            'tag_ids' => [$tagA->id, $tagB->id],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('client_tag', ['client_id' => $client->id, 'tag_id' => $tagA->id]);
        $this->assertDatabaseHas('client_tag', ['client_id' => $client->id, 'tag_id' => $tagB->id]);
    }

    public function test_view_permission_is_required_to_list_clients(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.view');

        $this->getJson('/api/clients', $headers)->assertForbidden();
    }

    public function test_manage_permission_is_required_to_update_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.manage');
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}", ['full_name' => 'X'], $headers)->assertForbidden();
    }

    public function test_manage_permission_is_required_to_delete_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.manage');
        $client = Client::factory()->create();

        $this->deleteJson("/api/clients/{$client->id}", [], $headers)->assertForbidden();
    }
}
