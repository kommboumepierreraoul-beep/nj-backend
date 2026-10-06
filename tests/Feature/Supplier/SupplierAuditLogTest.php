<?php

namespace Tests\Feature\Supplier;

use App\Enums\SupplierVerificationMethod;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Verifie que les actions sensibles du module Fournisseurs/RFQ (creation, mise
 * a jour et suppression d'un fournisseur, verification, mise sur liste noire,
 * creation d'une RFQ, selection d'un devis fournisseur) ecrivent bien une
 * ligne dans audit_logs, avec le couple action/entity_type attendu (voir
 * App\Models\AuditLog::record()). Complements SupplierTest et RfqTest, qui
 * couvrent deja le comportement metier lui-meme.
 */
class SupplierAuditLogTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_creating_a_supplier_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/suppliers', [
            'company_name' => 'Shenzhen Bright Electronics Co.',
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Supplier',
            'entity_id' => $response->json('data.id'),
            'action' => 'supplier.created',
        ]);
    }

    public function test_updating_a_supplier_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create(['company_name' => 'Nom Initial']);

        $this->putJson("/api/suppliers/{$supplier->id}", [
            'company_name' => 'Nom Mis A Jour',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->id,
            'action' => 'supplier.updated',
        ]);
    }

    public function test_deleting_a_supplier_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->deleteJson("/api/suppliers/{$supplier->id}", [], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->id,
            'action' => 'supplier.deleted',
        ]);
    }

    public function test_verifying_a_supplier_logs_an_audit_entry_with_is_verified_true(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/verify", [
            'verification_method' => SupplierVerificationMethod::VIDEO_CALL->value,
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->id,
            'action' => 'supplier.verified',
        ]);

        $log = AuditLog::query()
            ->where('entity_type', 'Supplier')
            ->where('entity_id', $supplier->id)
            ->where('action', 'supplier.verified')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->new_value_json['is_verified']);
    }

    public function test_blacklisting_a_supplier_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/blacklist", [
            'is_blacklisted' => true,
            'blacklist_reason' => 'Retards de livraison repetes',
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Supplier',
            'entity_id' => $supplier->id,
            'action' => 'supplier.blacklist_updated',
        ]);
    }

    public function test_creating_an_rfq_logs_an_audit_entry(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->postJson('/api/rfqs', [
            'request_date' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Rfq',
            'entity_id' => $response->json('data.id'),
            'action' => 'rfq.created',
        ]);
    }

    public function test_selecting_a_quote_logs_an_audit_entry_and_deselects_the_other_quotes(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        $item = RfqItem::factory()->create(['rfq_id' => $rfq->id]);
        $currency = Currency::factory()->create();

        // Deux devis sur la meme ligne de RFQ, pour verifier au passage l'effet
        // de bord "un seul devis retenu par ligne" declenche par select().
        $supplierA = Supplier::factory()->create();
        $rfqSupplierA = $rfq->rfqSuppliers()->create(['supplier_id' => $supplierA->id]);
        $quoteAId = $this->postJson("/api/rfq-suppliers/{$rfqSupplierA->id}/quotes", [
            'rfq_item_id' => $item->id,
            'quoted_unit_price' => 4.5,
            'currency_id' => $currency->id,
            'quoted_at' => now()->toDateTimeString(),
        ], $headers)->assertCreated()->json('data.id');

        $supplierB = Supplier::factory()->create();
        $rfqSupplierB = $rfq->rfqSuppliers()->create(['supplier_id' => $supplierB->id]);
        $quoteBId = $this->postJson("/api/rfq-suppliers/{$rfqSupplierB->id}/quotes", [
            'rfq_item_id' => $item->id,
            'quoted_unit_price' => 4.2,
            'currency_id' => $currency->id,
            'quoted_at' => now()->toDateTimeString(),
        ], $headers)->json('data.id');

        $this->postJson("/api/rfq-suppliers/{$rfqSupplierB->id}/quotes/{$quoteBId}/select", [], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'RfqSupplierQuote',
            'entity_id' => $quoteBId,
            'action' => 'rfq_supplier_quote.selected',
        ]);
        $this->assertDatabaseHas('rfq_supplier_quotes', ['id' => $quoteBId, 'is_selected' => true]);
        $this->assertDatabaseHas('rfq_supplier_quotes', ['id' => $quoteAId, 'is_selected' => false]);
    }
}
