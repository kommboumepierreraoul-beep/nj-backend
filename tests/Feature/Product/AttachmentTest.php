<?php

namespace Tests\Feature\Product;

use App\Enums\AttachmentType;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_upload_a_product_attachment_with_several_media_types(): void
    {
        Storage::fake('public');
        [$admin, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/attachments', [
            'attachable_type' => 'product',
            'attachable_id' => $product->id,
            'file' => UploadedFile::fake()->image('fiche-produit.jpg'),
            'media_types' => [AttachmentType::PRODUCT_IMAGE->value, AttachmentType::OTHER->value],
        ], $headers)->assertCreated();

        $attachmentId = $response->json('data.id');

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'attachable_type' => Product::class,
            'attachable_id' => $product->id,
            'uploaded_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('attachment_media_types', ['attachment_id' => $attachmentId, 'type' => AttachmentType::PRODUCT_IMAGE->value]);
        $this->assertDatabaseHas('attachment_media_types', ['attachment_id' => $attachmentId, 'type' => AttachmentType::OTHER->value]);
    }

    public function test_uploading_an_attachment_for_an_unknown_supplier_fails(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/attachments', [
            'attachable_type' => 'supplier',
            'attachable_id' => 999999,
            'file' => UploadedFile::fake()->create('document.pdf', 100),
            'media_types' => [AttachmentType::SUPPLIER_DOCUMENT->value],
        ], $headers)->assertNotFound();
    }

    public function test_admin_can_replace_the_media_types_of_an_attachment(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $created = $this->postJson('/api/attachments', [
            'attachable_type' => 'supplier',
            'attachable_id' => $supplier->id,
            'file' => UploadedFile::fake()->create('licence.pdf', 200),
            'media_types' => [AttachmentType::SUPPLIER_DOCUMENT->value],
        ], $headers)->json('data.id');

        $this->putJson("/api/attachments/{$created}", [
            'media_types' => [AttachmentType::OTHER->value],
        ], $headers)->assertOk();

        $this->assertDatabaseMissing('attachment_media_types', ['attachment_id' => $created, 'type' => AttachmentType::SUPPLIER_DOCUMENT->value]);
        $this->assertDatabaseHas('attachment_media_types', ['attachment_id' => $created, 'type' => AttachmentType::OTHER->value]);
    }

    public function test_admin_can_delete_an_attachment(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $created = $this->postJson('/api/attachments', [
            'attachable_type' => 'product',
            'attachable_id' => $product->id,
            'file' => UploadedFile::fake()->image('a-supprimer.jpg'),
            'media_types' => [AttachmentType::PRODUCT_IMAGE->value],
        ], $headers)->json('data.id');

        $this->deleteJson("/api/attachments/{$created}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('attachments', ['id' => $created]);
        $this->assertDatabaseMissing('attachment_media_types', ['attachment_id' => $created]);
    }
}
