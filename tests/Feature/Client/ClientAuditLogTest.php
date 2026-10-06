<?php

namespace Tests\Feature\Client;

use App\Enums\ClientStatus;
use App\Enums\ValueSegment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Verifie que les actions sensibles du module Clients (creation/mise a jour/
 * suppression de client, categorie de client, canal de contact, changement de
 * statut, de segment de valeur et synchronisation des etiquettes) ecrivent
 * bien une ligne dans audit_logs, avec le couple action/entity_type attendu
 * (voir App\Models\AuditLog::record()).
 */
class ClientAuditLogTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_creating_a_client_category_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/client-categories', [
            'code' => 'ECOM_RICH',
            'label' => 'Ecom-Rich',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ClientCategory',
            'entity_id' => $response->json('data.id'),
            'action' => 'client_category.created',
        ]);
    }

    public function test_creating_a_client_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/clients', [
            'full_name' => 'Jean Dupont',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $response->json('data.id'),
            'action' => 'client.created',
        ]);
    }

    public function test_updating_a_client_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create(['full_name' => 'Nom Initial']);

        $this->putJson("/api/clients/{$client->id}", [
            'full_name' => 'Nom Mis A Jour',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $client->id,
            'action' => 'client.updated',
        ]);
    }

    public function test_deleting_a_client_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->deleteJson("/api/clients/{$client->id}", [], $headers)->assertOk();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $client->id,
            'action' => 'client.deleted',
        ]);
    }

    public function test_changing_a_client_status_logs_an_audit_entry_with_the_new_status(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create(['status' => ClientStatus::ACTIF->value]);

        $this->putJson("/api/clients/{$client->id}/status", [
            'status' => ClientStatus::INACTIF->value,
        ], $headers)->assertOk()->assertJsonPath('data.status', ClientStatus::INACTIF->value);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $client->id,
            'action' => 'client.status_changed',
        ]);

        $log = AuditLog::query()
            ->where('entity_type', 'Client')
            ->where('entity_id', $client->id)
            ->where('action', 'client.status_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(['status' => ClientStatus::INACTIF->value], $log->new_value_json);
        $this->assertSame(['status' => ClientStatus::ACTIF->value], $log->old_value_json);
    }

    public function test_changing_a_client_value_segment_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->putJson("/api/clients/{$client->id}/value-segment", [
            'value_segment' => ValueSegment::VIP->value,
        ], $headers)->assertOk()->assertJsonPath('data.value_segment', ValueSegment::VIP->value);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $client->id,
            'action' => 'client.value_segment_changed',
        ]);
    }

    public function test_syncing_a_client_tags_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $tag = Tag::query()->create(['name' => 'Grossiste', 'slug' => 'grossiste', 'color' => '#6B7280']);

        $this->putJson("/api/clients/{$client->id}/tags", [
            'tag_ids' => [$tag->id],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Client',
            'entity_id' => $client->id,
            'action' => 'client.tags_synced',
        ]);
    }

    public function test_creating_a_contact_channel_type_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/contact-channel-types', [
            'code' => 'FACEBOOK',
            'label' => 'Facebook',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ContactChannelType',
            'entity_id' => $response->json('data.id'),
            'action' => 'contact_channel_type.created',
        ]);
    }
}
