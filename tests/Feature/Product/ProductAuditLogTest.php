<?php

namespace Tests\Feature\Product;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Verifie que les actions d'ecriture du module Produits (creation d'une
 * categorie, mise a jour/suppression d'un produit, synchronisation de ses
 * etiquettes, suppression d'une piece jointe non liee a une facture) ecrivent
 * bien une ligne dans audit_logs, avec le couple action/entity_type attendu
 * (voir App\Models\AuditLog::record()). Complements ProductCategoryTest,
 * ProductTest, TagTest et AttachmentTest, qui couvrent deja le comportement
 * metier lui-meme.
 */
class ProductAuditLogTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_creating_a_product_category_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/product-categories', [
            'name' => 'Electromenager',
            'slug' => 'electromenager',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'ProductCategory',
            'entity_id' => $response->json('data.id'),
            'action' => 'product_category.created',
        ]);
    }

    public function test_updating_a_product_logs_an_audit_entry_with_old_and_new_values(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create(['name' => 'Ancien nom']);

        $this->putJson("/api/products/{$product->id}", [
            'name' => 'Nouveau nom produit',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Product',
            'entity_id' => $product->id,
            'action' => 'product.updated',
        ]);

        $log = AuditLog::query()
            ->where('entity_type', 'Product')
            ->where('entity_id', $product->id)
            ->where('action', 'product.updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Ancien nom', $log->old_value_json['name']);
        $this->assertSame('Nouveau nom produit', $log->new_value_json['name']);
    }

    public function test_deleting_a_product_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}", [], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Product',
            'entity_id' => $product->id,
            'action' => 'product.deleted',
        ]);
    }

    public function test_syncing_a_products_tags_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $tag = Tag::query()->create(['name' => 'Promo', 'slug' => 'promo']);

        $this->putJson("/api/products/{$product->id}/tags", [
            'tag_ids' => [$tag->id],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Product',
            'entity_id' => $product->id,
            'action' => 'product.tags_synced',
        ]);
    }

    public function test_deleting_a_non_invoice_attachment_logs_attachment_deleted_via_the_general_branch(): void
    {
        // Regression cible : avant ce lot, seule la suppression d'une piece jointe
        // de facture etait journalisee (AttachmentController::destroy()). On verifie
        // ici que le nouveau chemin general (produit/fournisseur/client/commande)
        // ecrit bien "attachment.deleted", et jamais "invoice.attachment_deleted".
        Storage::fake('public');
        [$admin, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();

        $attachment = Attachment::query()->create([
            'attachable_type' => Product::class,
            'attachable_id' => $product->id,
            'file_name' => 'fiche-produit.pdf',
            'file_path' => 'attachments/product/fiche-produit.pdf',
            'mime_type' => 'application/pdf',
            'size_kb' => 12,
            'is_primary' => false,
            'sort_order' => 0,
            'uploaded_by_user_id' => $admin->id,
            'uploaded_at' => now(),
        ]);

        $this->deleteJson("/api/attachments/{$attachment->id}", [], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Attachment',
            'entity_id' => $attachment->id,
            'action' => 'attachment.deleted',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'entity_id' => $attachment->id,
            'action' => 'invoice.attachment_deleted',
        ]);
    }
}
