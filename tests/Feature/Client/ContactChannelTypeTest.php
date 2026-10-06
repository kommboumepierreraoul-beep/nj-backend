<?php

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\ContactChannelType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ContactChannelTypeTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_default_channels_are_seeded_by_the_migration(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/contact-channel-types', $headers)->assertOk();

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('EMAIL'));
        $this->assertTrue($codes->contains('PHONE'));
        $this->assertTrue($codes->contains('WHATSAPP'));
    }

    public function test_admin_can_create_a_new_contact_channel_type(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/contact-channel-types', [
            'code' => 'FACEBOOK',
            'label' => 'Facebook',
        ], $headers)->assertCreated()->assertJsonPath('data.code', 'FACEBOOK');

        $this->assertDatabaseHas('contact_channel_types', ['code' => 'FACEBOOK']);
    }

    public function test_contact_channel_type_code_must_be_unique(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/contact-channel-types', [
            'code' => 'EMAIL',
            'label' => 'Doublon',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_update_a_contact_channel_type(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $channel = ContactChannelType::query()->where('code', 'WHATSAPP')->firstOrFail();

        $this->putJson("/api/contact-channel-types/{$channel->id}", [
            'label' => 'WhatsApp Business',
        ], $headers)->assertOk()->assertJsonPath('data.label', 'WhatsApp Business');
    }

    public function test_a_contact_channel_type_used_by_contacts_cannot_be_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $channel = ContactChannelType::query()->where('code', 'EMAIL')->firstOrFail();
        $client->contacts()->create(['channel_type_id' => $channel->id, 'value' => 'client@test.test']);

        $this->deleteJson("/api/contact-channel-types/{$channel->id}", [], $headers)->assertStatus(409);
    }

    public function test_admin_can_delete_an_unused_contact_channel_type(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $channel = ContactChannelType::query()->create(['code' => 'WECHAT', 'label' => 'WeChat']);

        $this->deleteJson("/api/contact-channel-types/{$channel->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('contact_channel_types', ['id' => $channel->id]);
    }

    public function test_manage_permission_is_required_to_create_a_contact_channel_type(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('clients.manage');

        $this->postJson('/api/contact-channel-types', [
            'code' => 'INSTAGRAM',
            'label' => 'Instagram',
        ], $headers)->assertForbidden();
    }
}
