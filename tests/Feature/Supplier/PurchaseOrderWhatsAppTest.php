<?php

namespace Tests\Feature\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Models\CompanySettings;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Envoi manuel de la commande fournisseur par WhatsApp (Doc/communication_whatsapp_manuelle.md),
 * App\Http\Controllers\Supplier\PurchaseOrderController::sendWhatsapp(). Trace via
 * SupplierCommunicationLog (deja existant), pas de nouvelle colonne sent_at.
 */
class PurchaseOrderWhatsAppTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_send_the_purchase_order_to_the_supplier_by_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.purchase_order_send' => 555]);
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000003']);
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/send-whatsapp", [], $headers)
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request['templateId'] === 555
                && $request['senderNumber'] === '+237699999999'
                && $request['contactNumbers'] === ['+237600000003'];
        });

        $this->assertDatabaseHas('supplier_communication_logs', [
            'supplier_id' => $supplier->id,
            'channel' => CommunicationChannel::WHATSAPP->value,
            'direction' => CommunicationDirection::OUTGOING->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.sent_whatsapp']);
    }

    public function test_send_whatsapp_is_refused_when_supplier_has_no_whatsapp_number(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.purchase_order_send' => 555]);
        $supplier = Supplier::factory()->create(['whatsapp' => null]);
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        Http::fake();

        $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_send_whatsapp_is_refused_when_no_template_is_configured(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.purchase_order_send' => null]);
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000003']);
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        Http::fake();

        $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_manage_permission_is_required_to_send_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('purchase_orders.manage');
        $supplier = Supplier::factory()->create(['whatsapp' => '+237600000003']);
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

        $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/send-whatsapp", [], $headers)
            ->assertForbidden();
    }
}
