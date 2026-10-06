<?php

namespace Tests\Feature\Invoice;

use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Documents PDF bilingues (Doc/documents_bilingues_addendum.md) : la langue de
 * rendu vient de `invoices.language`, elle-meme derivee de
 * `clients.preferred_language` ou d'un `language` explicite a l'emission. Les
 * libelles sont dans lang/{fr,en}/documents.php et le rendu Blade est fait avec
 * `App::setLocale()` positionne sur la langue du document.
 */
class DocumentLanguageTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeOrder(string $clientLanguage): SalesOrder
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]
        );

        $client = Client::factory()->create(['preferred_language' => $clientLanguage]);

        $order = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'status' => SalesOrderStatus::BROUILLON->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        $order->items()->create([
            'item_type' => 'SERVICE',
            'label' => 'Sourcing',
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => 0,
            'subtotal' => 100000,
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        return $order;
    }

    /** Rend le gabarit proforma d'une facture dans la langue de celle-ci, comme le controleur. */
    private function renderProforma(Invoice $invoice): string
    {
        $invoice->load(['items', 'client', 'currency', 'salesOrder']);
        $previous = App::getLocale();
        App::setLocale($invoice->language->value === 'EN' ? 'en' : 'fr');
        try {
            return View::make('pdf.invoice_proforma', [
                'invoice' => $invoice,
                'company' => CompanySettings::current(),
                'paymentMethods' => collect(),
                'logoBase64' => null,
            ])->render();
        } finally {
            App::setLocale($previous);
        }
    }

    public function test_english_client_gets_an_english_proforma(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeOrder('EN');

        $id = $this->postJson("/api/sales-orders/{$order->id}/proforma", [], $headers)
            ->assertCreated()
            ->json('data.id');

        $invoice = Invoice::findOrFail($id);
        $this->assertSame('EN', $invoice->language->value);
        // Mentions legales figees a l'emission, dans la langue du document.
        $this->assertStringContainsString('This document is a quotation', $invoice->legal_mentions);

        $html = $this->renderProforma($invoice);
        $this->assertStringContainsString('PROFORMA INVOICE', $html);
        $this->assertStringContainsString('Issued by', $html);
        $this->assertStringContainsString('Description', $html);
        $this->assertStringNotContainsString('Émis par', $html);
    }

    public function test_french_client_gets_a_french_proforma_by_default(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeOrder('FR');

        $id = $this->postJson("/api/sales-orders/{$order->id}/proforma", [], $headers)
            ->assertCreated()->json('data.id');

        $invoice = Invoice::findOrFail($id);
        $this->assertSame('FR', $invoice->language->value);
        $this->assertStringContainsString('devis (proforma)', $invoice->legal_mentions);

        $html = $this->renderProforma($invoice);
        $this->assertStringContainsString('DEVIS PROFORMA', $html);
        $this->assertStringContainsString('Émis par', $html);
    }

    public function test_explicit_language_overrides_the_client_preference(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeOrder('FR');

        $id = $this->postJson("/api/sales-orders/{$order->id}/proforma", ['language' => 'EN'], $headers)
            ->assertCreated()->json('data.id');

        $invoice = Invoice::findOrFail($id);
        $this->assertSame('EN', $invoice->language->value);
        $this->assertStringContainsString('PROFORMA INVOICE', $this->renderProforma($invoice));
    }

    public function test_invalid_language_is_rejected(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeOrder('FR');

        $this->postJson("/api/sales-orders/{$order->id}/proforma", ['language' => 'ES'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('language');
    }

    public function test_credit_note_inherits_the_credited_document_language(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeOrder('EN');

        $proformaId = $this->postJson("/api/sales-orders/{$order->id}/proforma", [], $headers)
            ->assertCreated()->json('data.id');

        $creditNoteId = $this->postJson("/api/invoices/{$proformaId}/credit-notes", [], $headers)
            ->assertCreated()->json('data.id');

        $creditNote = Invoice::findOrFail($creditNoteId);
        $this->assertSame('EN', $creditNote->language->value);
        $this->assertStringContainsString('This credit note cancels', $creditNote->legal_mentions);
    }

    public function test_document_lang_files_have_matching_key_sets(): void
    {
        $fr = require base_path('lang/fr/documents.php');
        $en = require base_path('lang/en/documents.php');

        $flatten = function ($array, $prefix = '') use (&$flatten) {
            $keys = [];
            foreach ($array as $key => $value) {
                $path = $prefix === '' ? $key : "{$prefix}.{$key}";
                $keys = array_merge($keys, is_array($value) ? $flatten($value, $path) : [$path]);
            }

            return $keys;
        };

        $frKeys = $flatten($fr);
        $enKeys = $flatten($en);
        sort($frKeys);
        sort($enKeys);

        $this->assertSame($frKeys, $enKeys, 'lang/fr/documents.php and lang/en/documents.php must define the same keys.');
    }
}
