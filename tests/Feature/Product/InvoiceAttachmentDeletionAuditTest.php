<?php

namespace Tests\Feature\Product;

use App\Enums\SalesOrderStatus;
use App\Models\Attachment;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Ecart corrige le 2026-08-18 (Doc/factures_modele_donnees.md, section 9) :
 * AttachmentController::destroy() ne journalisait aucune suppression, alors que
 * l'emission/le remplacement d'un document Invoice le sont deja (voir
 * ProformaGenerationTest). Cette suppression est desormais tracee contre l'entite
 * Invoice elle-meme.
 */
class InvoiceAttachmentDeletionAuditTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_deleting_an_invoice_attachment_writes_an_audit_log(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();

        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]
        );
        $salesOrder = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::BROUILLON->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);
        $salesOrder->items()->create([
            'item_type' => 'SERVICE',
            'label' => 'Sourcing fournisseur',
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => 0,
            'subtotal' => 100000,
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        $invoiceId = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)
            ->assertCreated()->json('data.id');

        $attachment = Attachment::query()
            ->where('attachable_type', Invoice::class)
            ->where('attachable_id', $invoiceId)
            ->firstOrFail();

        $this->deleteJson("/api/attachments/{$attachment->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Invoice',
            'entity_id' => $invoiceId,
            'action' => 'invoice.attachment_deleted',
        ]);
    }
}
