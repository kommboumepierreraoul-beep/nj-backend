<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\ContactChannelType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Envoi manuel de documents/relances au client par WhatsApp
 * (Doc/communication_whatsapp_manuelle.md), App\Http\Controllers\Invoice\InvoiceController::
 * sendWhatsapp()/relanceWhatsapp(). Jamais automatise (§0/§2.4 du cahier des charges) : chaque
 * test declenche l'envoi via une requete HTTP explicite, comme le ferait un clic utilisateur.
 */
class InvoiceWhatsAppTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeClientWithWhatsapp(string $number = '+237600000001'): Client
    {
        $client = Client::factory()->create();
        $whatsapp = ContactChannelType::query()->where('code', 'WHATSAPP')->firstOrFail();
        $client->contacts()->create(['channel_type_id' => $whatsapp->id, 'value' => $number, 'is_preferred' => true]);

        return $client;
    }

    private function configureTemplate(string $eventKey, int $templateId = 111): void
    {
        config(["services.brevo.whatsapp_templates.{$eventKey}" => $templateId]);
    }

    public function test_admin_can_send_a_proforma_to_the_client_by_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        $this->configureTemplate('proforma_send');
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'document_type' => InvoiceDocumentType::PROFORMA->value,
            'status' => InvoiceStatus::EMISE->value,
        ]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/whatsapp/sendMessage'
                && $request['templateId'] === 111
                && $request['senderNumber'] === '+237699999999'
                && $request['contactNumbers'] === ['+237600000001'];
        });

        $invoice->refresh();
        $this->assertNotNull($invoice->sent_at);
        $this->assertSame(InvoiceStatus::ENVOYEE, $invoice->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.sent_whatsapp']);
    }

    public function test_send_whatsapp_uses_facture_template_for_a_facture_document(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        $this->configureTemplate('facture_send', 222);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'document_type' => InvoiceDocumentType::FACTURE->value,
            'status' => InvoiceStatus::EMISE->value,
        ]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)->assertOk();

        Http::assertSent(fn ($request) => $request['templateId'] === 222);
    }

    public function test_send_whatsapp_is_refused_when_no_template_is_configured(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        config(['services.brevo.whatsapp_templates.proforma_send' => null]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);
        Http::fake();

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertNull($invoice->fresh()->sent_at);
    }

    public function test_send_whatsapp_is_refused_when_client_has_no_whatsapp_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        $this->configureTemplate('proforma_send');
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);
        Http::fake();

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_send_whatsapp_is_refused_for_a_replaced_or_cancelled_document(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        $this->configureTemplate('proforma_send');
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => InvoiceStatus::REMPLACEE->value,
        ]);
        Http::fake();

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_send_whatsapp_is_refused_for_an_avoir(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'document_type' => InvoiceDocumentType::AVOIR->value,
        ]);
        Http::fake();

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_admin_can_send_a_relance_for_a_pending_proforma(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        CompanySettings::current()->update(['whatsapp' => '+237699999999']);
        $this->configureTemplate('proforma_relance', 333);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'document_type' => InvoiceDocumentType::PROFORMA->value,
            'status' => InvoiceStatus::ENVOYEE->value,
        ]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->postJson("/api/invoices/{$invoice->id}/relance-whatsapp", [], $headers)->assertOk();

        Http::assertSent(fn ($request) => $request['templateId'] === 333);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.relance_whatsapp_sent']);
    }

    public function test_relance_whatsapp_is_refused_for_a_facture(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = $this->makeClientWithWhatsapp();
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'document_type' => InvoiceDocumentType::FACTURE->value,
        ]);
        Http::fake();

        $this->postJson("/api/invoices/{$invoice->id}/relance-whatsapp", [], $headers)
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_manage_permission_is_required_to_send_whatsapp(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.manage');
        $client = $this->makeClientWithWhatsapp();
        $invoice = Invoice::factory()->create(['client_id' => $client->id]);

        $this->postJson("/api/invoices/{$invoice->id}/send-whatsapp", [], $headers)->assertForbidden();
    }
}
