<?php

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\ContactChannelType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ClientContactTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_contact_for_a_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $whatsapp = ContactChannelType::query()->where('code', 'WHATSAPP')->firstOrFail();

        $this->postJson("/api/clients/{$client->id}/contacts", [
            'channel_type_id' => $whatsapp->id,
            'value' => '+237600000000',
            'is_preferred' => true,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'channel_type_id' => $whatsapp->id,
            'value' => '+237600000000',
        ]);
    }

    public function test_setting_a_contact_as_preferred_unsets_the_previous_preferred_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $email = ContactChannelType::query()->where('code', 'EMAIL')->firstOrFail();
        $whatsapp = ContactChannelType::query()->where('code', 'WHATSAPP')->firstOrFail();
        $first = $client->contacts()->create(['channel_type_id' => $email->id, 'value' => 'client@test.test', 'is_preferred' => true]);

        $created = $this->postJson("/api/clients/{$client->id}/contacts", [
            'channel_type_id' => $whatsapp->id,
            'value' => '+237600000000',
            'is_preferred' => true,
        ], $headers)->json('data.id');

        $this->assertDatabaseHas('client_contacts', ['id' => $created, 'is_preferred' => true]);
        $this->assertDatabaseHas('client_contacts', ['id' => $first->id, 'is_preferred' => false]);
    }

    public function test_admin_can_list_update_and_delete_a_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $phone = ContactChannelType::query()->where('code', 'PHONE')->firstOrFail();
        $contact = $client->contacts()->create(['channel_type_id' => $phone->id, 'value' => '+237600000001']);

        $this->getJson("/api/clients/{$client->id}/contacts", $headers)->assertOk();

        $this->putJson("/api/clients/{$client->id}/contacts/{$contact->id}", [
            'value' => '+237600000099',
        ], $headers)->assertOk();

        $this->deleteJson("/api/clients/{$client->id}/contacts/{$contact->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('client_contacts', ['id' => $contact->id]);
    }

    public function test_creating_a_contact_validates_the_channel_type(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->postJson("/api/clients/{$client->id}/contacts", [
            'channel_type_id' => 999999,
            'value' => 'inconnu',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['channel_type_id']);
    }

    public function test_manage_permission_is_required_to_add_a_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.manage');
        $client = Client::factory()->create();
        $email = ContactChannelType::query()->where('code', 'EMAIL')->firstOrFail();

        $this->postJson("/api/clients/{$client->id}/contacts", [
            'channel_type_id' => $email->id,
            'value' => 'client@test.test',
        ], $headers)->assertForbidden();
    }
}
