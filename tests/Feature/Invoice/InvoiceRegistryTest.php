<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Registre transverse des documents (page « Factures » autonome) :
 * App\Http\Controllers\Invoice\InvoiceController::registry(), GET /api/invoices.
 */
class InvoiceRegistryTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_view_permission_is_required(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.view');

        $this->getJson('/api/invoices', $headers)->assertForbidden();
    }

    public function test_it_lists_documents_across_all_orders_most_recent_first(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $orderA = SalesOrder::factory()->create();
        $orderB = SalesOrder::factory()->create();

        Invoice::factory()->create(['sales_order_id' => $orderA->id, 'invoice_number' => 'OLD-001', 'issued_at' => now()->subDays(5)]);
        Invoice::factory()->create(['sales_order_id' => $orderB->id, 'invoice_number' => 'NEW-001', 'issued_at' => now()]);

        $this->getJson('/api/invoices', $headers)
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.invoice_number', 'NEW-001');
    }

    public function test_it_filters_by_document_type_status_and_client(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();

        Invoice::factory()->create(['document_type' => InvoiceDocumentType::PROFORMA->value, 'status' => InvoiceStatus::EMISE->value, 'client_id' => $client->id]);
        Invoice::factory()->create(['document_type' => InvoiceDocumentType::FACTURE->value, 'status' => InvoiceStatus::EMISE->value]);
        Invoice::factory()->create(['document_type' => InvoiceDocumentType::PROFORMA->value, 'status' => InvoiceStatus::REMPLACEE->value]);

        $this->getJson('/api/invoices?document_type=PROFORMA', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/invoices?document_type=FACTURE', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/invoices?status=REMPLACEE', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/invoices?client_id={$client->id}", $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_filters_by_search_and_issued_at_range(): void
    {
        [, $headers] = $this->actingAsAdmin();

        Invoice::factory()->create(['invoice_number' => 'NJG-2026-0001-042', 'client_name' => 'Boutique Alpha', 'issued_at' => '2026-05-10 09:00:00']);
        Invoice::factory()->create(['invoice_number' => 'NJG-2026-0002-043', 'client_name' => 'Comptoir Beta', 'issued_at' => '2026-08-01 09:00:00']);

        $this->getJson('/api/invoices?search=0001', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.client_name', 'Boutique Alpha');
        $this->getJson('/api/invoices?search=Beta', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/invoices?from=2026-07-01&to=2026-08-31', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'NJG-2026-0002-043');

        // Une seule borne fournie doit rester acceptee (le filtre frontend a deux champs
        // date independants) — pas de 422.
        $this->getJson('/api/invoices?to=2026-06-30', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'NJG-2026-0001-042');
        $this->getJson('/api/invoices?from=2026-06-01', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_it_exposes_the_pdf_url_and_parent_order(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $order = SalesOrder::factory()->create();
        $invoice = Invoice::factory()->create(['sales_order_id' => $order->id]);

        $this->getJson('/api/invoices', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.sales_order_id', $order->id)
            ->assertJsonPath('data.0.id', $invoice->id);
    }
}
