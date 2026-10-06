<?php

namespace Tests\Feature\Invoice;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\VariantLevel;
use App\Models\CompanySettings;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Arguments de la proforma comparative stockes en base et repris automatiquement a
 * l'emission (Doc/proforma_comparatif_addendum.md, demande du 2026-09-03 "simplifier les
 * taches de l'equipe") : points forts / attention / recommandation sur la fiche variante,
 * bloc "Notes / conditions" en defaut societe, surchargeables par l'emetteur.
 */
class ProformaProposalDefaultsTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function makeComparatifOrder(): SalesOrder
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'XAF'],
            ['name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true],
        );

        $product = Product::factory()->create();

        $order = SalesOrder::factory()->create([
            'status' => SalesOrderStatus::BROUILLON->value,
            'type' => SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX->value,
            'currency_id' => $currency->id,
            'subtotal_amount' => 200000,
            'total_amount' => 200000,
        ]);

        $variants = [
            VariantLevel::PREMIER_CHOIX->value => [
                'proforma_strengths' => ['Qualité supérieure', 'Finitions premium'],
                'proforma_weaknesses' => ['Prix plus élevé'],
                'proforma_recommendation' => 'Recommandé pour une clientèle exigeante.',
            ],
            VariantLevel::DEUXIEME_CHOIX->value => [
                'proforma_strengths' => ['Meilleur rapport qualité/prix'],
                'proforma_weaknesses' => [],
                'proforma_recommendation' => null,
            ],
            VariantLevel::TROISIEME_CHOIX->value => [
                'proforma_strengths' => ['Prix le plus bas'],
                'proforma_weaknesses' => ['Qualité d\'entrée de gamme'],
                'proforma_recommendation' => null,
            ],
        ];

        foreach ($variants as $level => $proforma) {
            $variant = ProductVariant::factory()->create(array_merge([
                'product_id' => $product->id,
                'level' => $level,
                'purchase_currency_id' => $currency->id,
            ], $proforma));

            $order->items()->create([
                'item_type' => 'PRODUIT',
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => 66000,
                'discount_amount' => 0,
                'subtotal' => 66000,
                'is_selected' => false,
                'sort_order' => 0,
            ]);
        }

        return $order;
    }

    public function test_variant_can_store_proforma_arguments(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $product = Product::factory()->create();
        $currency = Currency::factory()->create();

        $response = $this->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'SKU-PF-1',
            'name' => 'Variante premium',
            'level' => VariantLevel::PREMIER_CHOIX->value,
            'purchase_price' => 10,
            'purchase_currency_id' => $currency->id,
            'is_recommended' => false,
            'is_default' => false,
            'is_active' => true,
            'proforma_strengths' => ['Robuste', 'Garantie 2 ans'],
            'proforma_weaknesses' => ['Poids élevé'],
            'proforma_recommendation' => 'Idéale pour un usage intensif.',
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.proforma_strengths', ['Robuste', 'Garantie 2 ans'])
            ->assertJsonPath('data.proforma_recommendation', 'Idéale pour un usage intensif.');
    }

    public function test_proforma_defaults_endpoint_returns_values_from_variants_and_company_settings(): void
    {
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update([
            'default_proforma_conditions' => 'Prix DDP Douala.',
            'default_proforma_payment_terms' => '50 % à la commande, solde avant expédition.',
        ]);
        $order = $this->makeComparatifOrder();

        $this->getJson("/api/sales-orders/{$order->id}/proforma-defaults", $headers)
            ->assertOk()
            ->assertJsonPath('data.PREMIER_CHOIX.points_forts', ['Qualité supérieure', 'Finitions premium'])
            ->assertJsonPath('data.PREMIER_CHOIX.recommandation', 'Recommandé pour une clientèle exigeante.')
            ->assertJsonPath('data.TROISIEME_CHOIX.points_attention', ['Qualité d\'entrée de gamme'])
            ->assertJsonPath('data.notes.conditions_commerciales', 'Prix DDP Douala.')
            ->assertJsonPath('data.notes.paiement', '50 % à la commande, solde avant expédition.')
            ->assertJsonPath('data.notes.delai_production', null);
    }

    public function test_emitting_a_comparatif_proforma_without_payload_fills_proposal_details_from_the_database(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        CompanySettings::current()->update(['default_proforma_customs' => 'Dédouanement à la charge du client.']);
        $order = $this->makeComparatifOrder();

        $response = $this->postJson("/api/sales-orders/{$order->id}/proforma", [], $headers)->assertCreated();

        $response->assertJsonPath('data.proposal_details.PREMIER_CHOIX.points_forts', ['Qualité supérieure', 'Finitions premium'])
            ->assertJsonPath('data.proposal_details.DEUXIEME_CHOIX.points_forts', ['Meilleur rapport qualité/prix'])
            ->assertJsonPath('data.proposal_details.notes.douane_livraison', 'Dédouanement à la charge du client.');
    }

    public function test_emitter_payload_overrides_a_single_level_others_fall_back_to_the_variant(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();
        $order = $this->makeComparatifOrder();

        $response = $this->postJson("/api/sales-orders/{$order->id}/proforma", [
            'proposal_details' => [
                'PREMIER_CHOIX' => ['points_forts' => ['Argument sur mesure pour ce client']],
                'notes' => ['delai_production' => '3 semaines'],
            ],
        ], $headers)->assertCreated();

        // Niveau surcharge par l'emetteur.
        $response->assertJsonPath('data.proposal_details.PREMIER_CHOIX.points_forts', ['Argument sur mesure pour ce client']);
        // Recommandation du PREMIER_CHOIX non fournie -> repli sur la variante.
        $response->assertJsonPath('data.proposal_details.PREMIER_CHOIX.recommandation', 'Recommandé pour une clientèle exigeante.');
        // Autres niveaux inchanges -> variante.
        $response->assertJsonPath('data.proposal_details.TROISIEME_CHOIX.points_forts', ['Prix le plus bas']);
        // Note fournie -> surcharge ; note absente -> null (pas de defaut societe seedé ici).
        $response->assertJsonPath('data.proposal_details.notes.delai_production', '3 semaines');
        $response->assertJsonPath('data.proposal_details.notes.paiement', null);
    }

    public function test_invoices_view_permission_is_required_for_the_defaults_endpoint(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('invoices.view');
        $order = $this->makeComparatifOrder();

        $this->getJson("/api/sales-orders/{$order->id}/proforma-defaults", $headers)->assertForbidden();
    }
}
