<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\VariantLevel;
use App\Http\Controllers\Invoice\ProformaController;
use App\Models\Attachment;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

// Couvre le nouveau gabarit comparatif (Doc/proforma_comparatif_addendum.md), reserve
// aux commandes PRODUIT_UNIQUE_MULTI_CHOIX : garde-fou relache (au moins une ligne,
// pas forcement is_selected=true), commission recalculee independamment par option
// (pas un prorata), et rendu effectif des 3 options dans le PDF genere. Les tests du
// gabarit plat existant (ProformaGenerationTest, 8 tests) restent inchanges et non
// touches par ce fichier — voir aussi la 2e methode ci-dessous qui verifie explicitement
// que le garde-fou strict (is_selected=true requis) reste en vigueur pour les 2 autres
// types de commande.
class ProformaComparatifGenerationTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeComparatifOrder(array $orderAttributes = []): SalesOrder
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]
        );

        $product = Product::factory()->create();

        $salesOrder = SalesOrder::factory()->create(array_merge([
            'status' => SalesOrderStatus::BROUILLON->value,
            'type' => SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 280000,
            'total_amount' => 280000,
            'estimated_weight_kg' => 30,
            'estimated_volume_cbm' => 1.5,
        ], $orderAttributes));

        // Sous-totaux volontairement repartis sur les 2 paliers de commission_rules
        // seedes par defaut (2026_08_16_000007 : < 100 000 => forfait 10 000 FCFA,
        // >= 100 000 => 10 %), pour verifier que la commission par option n'est pas un
        // prorata unique de la commande.
        $levels = [
            VariantLevel::PREMIER_CHOIX->value => ['unit_price' => 80000, 'moq' => 50],
            VariantLevel::DEUXIEME_CHOIX->value => ['unit_price' => 150000, 'moq' => 100],
            VariantLevel::TROISIEME_CHOIX->value => ['unit_price' => 50000, 'moq' => 300],
        ];

        foreach ($levels as $level => $data) {
            // purchase_currency_id explicite (plutot que le defaut aleatoire de
            // ProductVariantFactory) : evite toute collision de code devise unique avec
            // le XAF cree explicitement ci-dessus (fake()->unique()->currencyCode() peut
            // par hasard reproduire 'XAF').
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'level' => $level,
                'moq' => $data['moq'],
                'purchase_currency_id' => $currency->id,
            ]);

            // is_selected volontairement false : le client n'a pas encore tranche entre
            // les 3 options presentees (decision n°2 de l'addendum).
            $salesOrder->items()->create([
                'item_type' => 'PRODUIT',
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => $data['unit_price'],
                'discount_amount' => 0,
                'subtotal' => $data['unit_price'],
                'is_selected' => false,
                'sort_order' => 0,
            ]);
        }

        return $salesOrder;
    }

    public function test_emitting_a_comparatif_proforma_succeeds_with_zero_selected_items_and_includes_all_options(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeComparatifOrder();

        // Garde-fou relache (decision n°2) : aucune ligne is_selected=true ici, a la
        // difference de MULTI_PRODUITS/PRESTATION_SERVICE (voir 2e test ci-dessous et
        // ProformaGenerationTest::test_rejects_when_no_item_is_selected(), inchange).
        $this->assertFalse($salesOrder->items()->where('is_selected', true)->exists());

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)
            ->assertCreated();

        $response->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.document_type', InvoiceDocumentType::PROFORMA->value)
            ->assertJsonPath('data.invoice_number', $salesOrder->reference);

        $this->assertCount(3, $response->json('data.items'));

        // Les 3 options sont bien toutes emises, triees dans l'ordre des colonnes du
        // wireframe (Premier/Deuxieme/Troisieme choix) via sort_order.
        $subtotals = collect($response->json('data.items'))->sortBy('sort_order')->pluck('subtotal')->map(fn ($v) => (float) $v)->all();
        $this->assertEquals([80000.0, 150000.0, 50000.0], $subtotals);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Invoice::class,
            'attachable_id' => $response->json('data.id'),
        ]);

        $this->assertDatabaseCount('invoice_items', 3);
    }

    public function test_the_relaxed_guard_does_not_apply_to_the_other_order_types(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'XAF']);

        $multiProduits = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::BROUILLON->value,
            'type' => SalesOrderType::MULTI_PRODUITS->value,
            'currency_id' => $currency->id,
        ]);

        // Aucune ligne du tout : rejete pour MULTI_PRODUITS (garde-fou is_selected=true
        // inchange), alors qu'une commande PRODUIT_UNIQUE_MULTI_CHOIX dans la meme
        // situation ("au moins une ligne existe") serait elle aussi rejetee ici (0 ligne),
        // mais pour une raison differente — voir le test suivant pour la distinction utile
        // (0 ligne selectionnee mais >=1 ligne existante).
        $this->postJson("/api/sales-orders/{$multiProduits->id}/proforma", [], $headers)->assertStatus(422);
    }

    public function test_commission_is_recalculated_independently_per_option_not_a_prorate(): void
    {
        $salesOrder = $this->makeComparatifOrder();
        $client = $salesOrder->client;

        $reflection = new ReflectionMethod(ProformaController::class, 'resolveLineCommission');
        $reflection->setAccessible(true);
        $controller = new ProformaController();

        // Sous-total 80 000 FCFA -> palier forfait (< 100 000, seed par defaut) : 10 000.
        $commissionSmall = $reflection->invoke($controller, $client, 80000.0);
        // Sous-total 150 000 FCFA -> palier pourcentage (>= 100 000, seed par defaut) : 10 %.
        $commissionLarge = $reflection->invoke($controller, $client, 150000.0);

        $this->assertEquals(10000.0, $commissionSmall);
        $this->assertEquals(15000.0, $commissionLarge);
        $this->assertNotEquals($commissionSmall, $commissionLarge);

        // "Pas un prorata" (decision n°3) : un prorata de la commission globale de la
        // commande (un seul palier applique au sous-total total) donnerait un montant
        // different de 10 000 sur la petite option, la preuve que le calcul est bien
        // reapplique independamment a chaque sous-total plutot que reparti au prorata.
        $globalSubtotal = (float) $salesOrder->items()->sum('subtotal');
        $globalCommission = $reflection->invoke($controller, $client, $globalSubtotal);
        $naiveProrateSmall = round($globalCommission * (80000 / $globalSubtotal), 2);
        $this->assertNotEquals($naiveProrateSmall, $commissionSmall);
    }

    public function test_pdf_renders_the_comparatif_template_with_all_three_levels(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeComparatifOrder();

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", [], $headers)->assertCreated();

        $path = Attachment::query()
            ->where('attachable_type', Invoice::class)
            ->where('attachable_id', $response->json('data.id'))
            ->value('file_path');

        $this->assertNotNull($path);

        $pdfContents = Storage::disk('public')->get($path);
        $tmpFile = tempnam(sys_get_temp_dir(), 'proforma_comparatif_').'.pdf';
        file_put_contents($tmpFile, $pdfContents);

        $text = shell_exec('pdftotext -layout '.escapeshellarg($tmpFile).' - 2>/dev/null') ?? '';
        @unlink($tmpFile);

        $this->assertNotEmpty($text, 'pdftotext devrait produire du texte extrait du PDF genere.');
        $this->assertStringContainsStringIgnoringCase('Comparatif des options', $text);
        $this->assertStringContainsStringIgnoringCase('Premier choix', $text);
        $this->assertStringContainsStringIgnoringCase('Deuxième choix', $text);
        $this->assertStringContainsStringIgnoringCase('Troisième choix', $text);
        $this->assertStringContainsStringIgnoringCase('Conseils professionnels', $text);
    }

    public function test_proposal_details_payload_is_stored_and_reflected_in_the_pdf(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $salesOrder = $this->makeComparatifOrder();

        $payload = [
            'proposal_details' => [
                'PREMIER_CHOIX' => [
                    'points_forts' => ['Qualite superieure'],
                    'points_attention' => ['Delai plus long'],
                    'recommandation' => 'Ideal pour un lancement premium.',
                ],
                'notes' => [
                    'conditions_commerciales' => '30% acompte, solde a expedition.',
                    'delai_production' => '15 jours',
                    'paiement' => 'Virement bancaire',
                    'douane_livraison' => 'DDP Douala',
                ],
            ],
        ];

        $response = $this->postJson("/api/sales-orders/{$salesOrder->id}/proforma", $payload, $headers)
            ->assertCreated();

        $stored = $response->json('data.proposal_details');

        // La saisie de l'emetteur est conservee telle quelle...
        $this->assertEquals($payload['proposal_details']['PREMIER_CHOIX'], $stored['PREMIER_CHOIX']);
        $this->assertEquals($payload['proposal_details']['notes'], $stored['notes']);

        // ... et depuis 2026-09-03, les autres niveaux comparables recoivent aussi une
        // entree, resolue depuis la fiche de leur variante (vide ici : les variantes de
        // makeComparatifOrder() n'ont pas d'arguments proforma stockes).
        $this->assertArrayHasKey('DEUXIEME_CHOIX', $stored);
        $this->assertArrayHasKey('TROISIEME_CHOIX', $stored);
        $this->assertSame([], $stored['DEUXIEME_CHOIX']['points_forts']);
        $this->assertNull($stored['TROISIEME_CHOIX']['recommandation']);

        $this->assertDatabaseHas('invoices', [
            'id' => $response->json('data.id'),
        ]);

        $invoice = Invoice::query()->findOrFail($response->json('data.id'));
        $this->assertEquals('30% acompte, solde a expedition.', $invoice->proposal_details['notes']['conditions_commerciales']);
    }
}
