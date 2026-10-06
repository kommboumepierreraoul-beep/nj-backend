<?php

namespace Tests\Feature\Client;

use App\Enums\AttachmentType;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class ClientAttachmentTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_upload_a_kyc_document_for_a_client(): void
    {
        Storage::fake('public');
        [$admin, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $response = $this->postJson('/api/attachments', [
            'attachable_type' => 'client',
            'attachable_id' => $client->id,
            'file' => UploadedFile::fake()->create('piece-identite.pdf', 150),
            'media_types' => [AttachmentType::CLIENT_DOCUMENT->value],
        ], $headers)->assertCreated();

        $attachmentId = $response->json('data.id');

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'attachable_type' => Client::class,
            'attachable_id' => $client->id,
            'uploaded_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('attachment_media_types', ['attachment_id' => $attachmentId, 'type' => AttachmentType::CLIENT_DOCUMENT->value]);
    }

    public function test_uploading_a_document_for_an_unknown_client_fails(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/attachments', [
            'attachable_type' => 'client',
            'attachable_id' => 999999,
            'file' => UploadedFile::fake()->create('document.pdf', 100),
            'media_types' => [AttachmentType::CLIENT_DOCUMENT->value],
        ], $headers)->assertNotFound();
    }

    public function test_a_client_document_appears_in_the_client_show_response(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->postJson('/api/attachments', [
            'attachable_type' => 'client',
            'attachable_id' => $client->id,
            'file' => UploadedFile::fake()->create('registre-commerce.pdf', 120),
            'media_types' => [AttachmentType::CLIENT_DOCUMENT->value],
        ], $headers)->assertCreated();

        $this->getJson("/api/clients/{$client->id}", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.attachments');
    }
}
