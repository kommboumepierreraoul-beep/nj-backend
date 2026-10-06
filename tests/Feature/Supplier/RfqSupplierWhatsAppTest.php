<?php

namespace Tests\Feature\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Models\CompanySettings;
use App\Models\Rfq;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Envoi manuel du RFQ a un fournisseur par WhatsApp (Doc/communication_whatsapp_manuelle.md),
 * App\Http\Controllers\Supplier\RfqSupplierController::sendWhatsapp(). Trace via
 * SupplierCommunicationLog (deja existant), pas de nouvelle table.
 */
class RfqSupplierWhatsAppTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_send_the_rfq_to_a_supplier_by_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.rfq_send' => 444]);
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000002']);
        $rfq = Rfq::factory()->create();
        $rfqSupplier = $rfq->rfqSuppliers()->create(['supplier_id' => $supplier->id, 'sent_at' => now()]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers/{$rfqSupplier->id}/send-whatsapp", [], $headers)
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request['templateId'] === 444
                && $request['senderNumber'] === '+237699999999'
                && $request['contactNumbers'] === ['+237600000002'];
        });

        $this->assertDatabaseHas('supplier_communication_logs', [
            'supplier_id' => $supplier->id,
            'channel' => CommunicationChannel::WHATSAPP->value,
            'direction' => CommunicationDirection::OUTGOING->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rfq_supplier.sent_whatsapp']);
    }

    public function test_send_whatsapp_is_refused_when_supplier_has_no_whatsapp_number(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.rfq_send' => 444]);
        $supplier = Supplier::factory()->create(['whatsapp' => null]);
        $rfq = Rfq::factory()->create();
        $rfqSupplier = $rfq->rfqSuppliers()->create(['supplier_id' => $supplier->id, 'sent_at' => now()]);
        Http::fake();

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers/{$rfqSupplier->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('supplier_communication_logs', ['supplier_id' => $supplier->id]);
    }

    public function test_send_whatsapp_404s_when_the_supplier_is_not_attached_to_this_rfq(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000002']);
        $otherRfq = Rfq::factory()->create();
        $rfq = Rfq::factory()->create();
        $rfqSupplier = $otherRfq->rfqSuppliers()->create(['supplier_id' => $supplier->id, 'sent_at' => now()]);

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers/{$rfqSupplier->id}/send-whatsapp", [], $headers)
            ->assertNotFound();
    }

    public function test_manage_permission_is_required_to_send_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('rfqs.manage');
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000002']);
        $rfq = Rfq::factory()->create();
        $rfqSupplier = $rfq->rfqSuppliers()->create(['supplier_id' => $supplier->id, 'sent_at' => now()]);

        $this->postJson("/api/rfqs/{$rfq->id}/suppliers/{$rfqSupplier->id}/send-whatsapp", [], $headers)
            ->assertForbidden();
    }
}
