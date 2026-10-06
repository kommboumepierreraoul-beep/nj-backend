<?php

namespace Tests\Feature\Supplier;

use App\Enums\AttachmentType;
use App\Enums\SupplierDocumentType;
use App\Models\Attachment;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierDocumentTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function createAttachmentFor(Supplier $supplier, int $uploadedByUserId): Attachment
    {
        $attachment = Attachment::query()->create([
            'attachable_type' => Supplier::class,
            'attachable_id' => $supplier->id,
            'file_name' => 'licence.pdf',
            'file_path' => 'attachments/supplier/licence.pdf',
            'mime_type' => 'application/pdf',
            'size_kb' => 120,
            'uploaded_by_user_id' => $uploadedByUserId,
            'uploaded_at' => now(),
        ]);
        $attachment->mediaTypes()->create(['type' => AttachmentType::SUPPLIER_DOCUMENT->value]);

        return $attachment;
    }

    public function test_admin_can_attach_a_document_to_a_supplier(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $attachment = $this->createAttachmentFor($supplier, $admin->id);

        $this->postJson("/api/suppliers/{$supplier->id}/documents", [
            'type' => SupplierDocumentType::BUSINESS_LICENSE->value,
            'attachment_id' => $attachment->id,
            'issue_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('supplier_documents', [
            'supplier_id' => $supplier->id,
            'attachment_id' => $attachment->id,
            'type' => SupplierDocumentType::BUSINESS_LICENSE->value,
        ]);
    }

    public function test_a_document_expiry_date_cannot_precede_its_issue_date(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $attachment = $this->createAttachmentFor($supplier, $admin->id);

        $this->postJson("/api/suppliers/{$supplier->id}/documents", [
            'type' => SupplierDocumentType::CERTIFICATE_ISO->value,
            'attachment_id' => $attachment->id,
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->subYear()->toDateString(),
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_list_update_and_delete_a_document(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $attachment = $this->createAttachmentFor($supplier, $admin->id);
        $document = $supplier->documents()->create([
            'type' => SupplierDocumentType::OTHER->value,
            'attachment_id' => $attachment->id,
        ]);

        $this->getJson("/api/suppliers/{$supplier->id}/documents", $headers)->assertOk();

        $this->putJson("/api/suppliers/{$supplier->id}/documents/{$document->id}", [
            'is_verified' => true,
        ], $headers)->assertOk()->assertJsonPath('data.is_verified', true);

        $this->deleteJson("/api/suppliers/{$supplier->id}/documents/{$document->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('supplier_documents', ['id' => $document->id]);
    }
}
