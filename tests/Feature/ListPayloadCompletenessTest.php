<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ContactChannelType;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqSupplier;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Champs que les pages de liste du frontend lisent (`row.x`) mais que l'API
 * n'exposait pas — correction du 2026-09-03. Chaque test échoue si le champ
 * repart absent (`null` / `0`).
 */
class ListPayloadCompletenessTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_purchase_order_list_includes_the_currency_object(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'CNY']);
        PurchaseOrder::factory()->create(['currency_id' => $currency->id]);

        $this->getJson('/api/purchase-orders', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.currency.code', 'CNY');
    }

    public function test_rfq_list_includes_item_and_supplier_counts(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        RfqItem::factory()->count(2)->create(['rfq_id' => $rfq->id]);
        RfqSupplier::query()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'status' => 'PENDING',
        ]);

        $this->getJson('/api/rfqs', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.suppliers_count', 1);
    }

    public function test_product_list_includes_the_variants_count(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

        $this->getJson('/api/products', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.variants_count', 3);
    }

    public function test_client_list_includes_the_preferred_contact_with_its_channel(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $channel = ContactChannelType::query()->firstOrCreate(['code' => 'EMAIL'], ['label' => 'Email', 'icon' => 'mail']);
        $client = Client::factory()->create();
        ClientContact::query()->create([
            'client_id' => $client->id,
            'channel_type_id' => $channel->id,
            'value' => 'pref@example.com',
            'label' => 'Principal',
            'is_preferred' => true,
        ]);
        ClientContact::query()->create([
            'client_id' => $client->id,
            'channel_type_id' => $channel->id,
            'value' => 'other@example.com',
            'is_preferred' => false,
        ]);

        $this->getJson('/api/clients', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.preferred_contact.value', 'pref@example.com')
            ->assertJsonPath('data.0.preferred_contact.channel_type.code', 'EMAIL');
    }
}
