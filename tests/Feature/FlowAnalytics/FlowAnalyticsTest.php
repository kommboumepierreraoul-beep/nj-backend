<?php

namespace Tests\Feature\FlowAnalytics;

use App\Enums\FlowThresholdType;
use App\Enums\FlowType;
use App\Enums\PaymentDirection;
use App\Enums\RfqSupplierStatus;
use App\Enums\SalesOrderItemType;
use App\Enums\SalesOrderPaymentMethod;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FlowStageThreshold;
use App\Models\ProductSupplier;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqSupplier;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Module "Analyse des flux" (Doc/analyse_flux_modele_donnees.md),
 * App\Http\Controllers\FlowAnalytics\FlowAnalyticsController.
 */
class FlowAnalyticsTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_view_flow_analytics(): void
    {
        $this->getJson('/api/flow-analytics/purchase-flow')->assertUnauthorized();
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('flow_analytics.view');

        $this->getJson('/api/flow-analytics/sales-flow', $headers)->assertForbidden();
    }

    public function test_purchase_flow_returns_the_expected_structure(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/flow-analytics/purchase-flow', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'period', 'range' => ['from', 'to'],
                'pipeline_actuel', 'reponse_fournisseur', 'performance_par_fournisseur',
                'devis', 'cycle_commande_fournisseur', 'rfq', 'temps_moyen_par_statut_jours', 'limite',
            ]);
    }

    // Taux/delai de reponse fournisseur (Doc/analyse_flux_modele_donnees.md, §1.1) : une
    // sollicitation repondue en 3 jours, une expiree sans reponse, sur la periode demandee.
    public function test_purchase_flow_computes_supplier_response_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $rfq = Rfq::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        RfqSupplier::query()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'status' => RfqSupplierStatus::RESPONDED->value,
            'sent_at' => '2020-05-01 09:00:00',
            'response_date' => '2020-05-04',
        ]);
        RfqSupplier::query()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierB->id,
            'status' => RfqSupplierStatus::EXPIRED->value,
            'sent_at' => '2020-05-02 09:00:00',
        ]);

        $response = $this->getJson('/api/flow-analytics/purchase-flow?period=month&date=2020-05-15', $headers)->assertOk();

        $response->assertJsonPath('reponse_fournisseur.sollicitations', 2);
        $response->assertJsonPath('reponse_fournisseur.reponses', 1);
        $response->assertJsonPath('reponse_fournisseur.expirees', 1);
        $response->assertJsonPath('reponse_fournisseur.taux_reponse_pourcentage', 50.0);
        $response->assertJsonPath('reponse_fournisseur.delai_moyen_reponse_jours', 3.0);
    }

    public function test_sales_flow_returns_the_expected_structure(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/flow-analytics/sales-flow', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'period', 'range' => ['from', 'to'],
                'pipeline_actuel', 'temps_moyen_par_statut_jours', 'abandons_par_etape',
                'commandes_creees', 'atteintes_par_etape', 'delai_moyen_paiement_jours',
            ]);
    }

    // Temps moyen par statut + abandon (Doc/analyse_flux_modele_donnees.md, §1.2), a partir
    // de sales_order_status_history : BROUILLON (2j) -> PROFORMA_ENVOYEE (2j) -> ANNULEE.
    public function test_sales_flow_computes_time_per_stage_and_cancellations(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $order = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::ANNULEE->value,
        ]);

        $order->statusHistory()->create(['from_status' => null, 'to_status' => SalesOrderStatus::BROUILLON->value, 'changed_by_user_id' => $admin->id, 'created_at' => '2020-05-01 09:00:00']);
        $order->statusHistory()->create(['from_status' => SalesOrderStatus::BROUILLON->value, 'to_status' => SalesOrderStatus::PROFORMA_ENVOYEE->value, 'changed_by_user_id' => $admin->id, 'created_at' => '2020-05-03 09:00:00']);
        $order->statusHistory()->create(['from_status' => SalesOrderStatus::PROFORMA_ENVOYEE->value, 'to_status' => SalesOrderStatus::ANNULEE->value, 'changed_by_user_id' => $admin->id, 'created_at' => '2020-05-05 09:00:00']);

        $response = $this->getJson('/api/flow-analytics/sales-flow?period=month&date=2020-05-15', $headers)->assertOk();

        $response->assertJsonPath('temps_moyen_par_statut_jours.BROUILLON', 2.0);
        $response->assertJsonPath('abandons_par_etape.PROFORMA_ENVOYEE', 1);
    }

    public function test_financial_flow_returns_the_expected_structure(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/flow-analytics/financial', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'period', 'range' => ['from', 'to'],
                'tresorerie' => ['encaisse', 'rembourse', 'net'],
                'exposition' => ['commandes_en_attente', 'montant_en_attente', 'age_moyen_jours'],
                'commission' => ['realisee', 'theorique_sur_commandes_creees'],
                'avoirs' => ['count', 'montant_total'],
            ]);
    }

    // Tresorerie + exposition (Doc/analyse_flux_modele_donnees.md, §1.3) : un encaissement
    // partiel dans la periode laisse la commande en attente.
    public function test_financial_flow_computes_treasury_and_exposure(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $order = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'order_date' => '2020-05-01',
            'total_amount' => 50000,
        ]);
        $order->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 30000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::ORANGE_MONEY->value,
            'receipt_number' => 'REC-FLOW-1',
            'paid_at' => '2020-05-10 10:00:00',
        ]);

        $response = $this->getJson('/api/flow-analytics/financial?period=month&date=2020-05-15', $headers)->assertOk();

        $response->assertJsonPath('tresorerie.encaisse', 30000.0);
        $response->assertJsonPath('exposition.commandes_en_attente', 1);
    }

    // Taux de transformation RFQ -> commande fournisseur (Doc/analyse_flux_modele_donnees.md,
    // §8.4) : desormais reel via purchase_orders.rfq_id. 2 RFQ dans la periode, une seule
    // aboutit a une commande fournisseur liee -> 50 %.
    public function test_purchase_flow_computes_real_rfq_to_purchase_order_conversion_rate(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $converted = Rfq::factory()->create(['request_date' => '2020-05-03']);
        Rfq::factory()->create(['request_date' => '2020-05-04']);
        PurchaseOrder::factory()->create(['rfq_id' => $converted->id]);

        $response = $this->getJson('/api/flow-analytics/purchase-flow?period=month&date=2020-05-15', $headers)->assertOk();

        $this->assertSame(2, $response->json('rfq.total'));
        $this->assertSame(1, $response->json('rfq.converties_en_commande'));
        $this->assertEquals(50, $response->json('rfq.taux_transformation_pourcentage'));
    }

    // Marge estimee (Doc/analyse_flux_modele_donnees.md, decision §0bis.2, livree en
    // version estimee) : cout = prix du fournisseur prefere (product_supplier). Ligne de
    // 10 unites vendue 1 500, cout catalogue 100/u -> marge 500.
    public function test_financial_flow_estimates_margin_from_preferred_supplier_price(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create(['code' => 'XAF']);
        $client = Client::factory()->create();
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();

        ProductSupplier::query()->create([
            'product_variant_id' => $variant->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 100,
            'currency_id' => $currency->id,
            'is_preferred' => true,
        ]);

        $order = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::LIVREE->value,
            'order_date' => '2020-05-02',
            'subtotal_amount' => 1500,
            'total_amount' => 1500,
        ]);
        $order->forceFill(['payment_status' => SalesOrderPaymentStatus::PAYEE->value])->save();

        $order->items()->create([
            'item_type' => SalesOrderItemType::PRODUIT->value,
            'product_variant_id' => $variant->id,
            'label' => 'Ligne',
            'quantity' => 10,
            'unit_price' => 150,
            'subtotal' => 1500,
            'is_proposed_option' => false,
        ]);

        $order->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 1500,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::ORANGE_MONEY->value,
            'receipt_number' => 'REC-MARGE-1',
            'paid_at' => '2020-05-10 10:00:00',
            'is_voided' => false,
        ]);

        $response = $this->getJson('/api/flow-analytics/financial?period=month&date=2020-05-15', $headers)->assertOk();

        $this->assertEquals(1500, $response->json('marge_estimee.ca_produits_estime'));
        $this->assertEquals(1000, $response->json('marge_estimee.cout_achat_estime'));
        $this->assertEquals(500, $response->json('marge_estimee.marge_estimee'));
        $this->assertSame(1, $response->json('marge_estimee.commandes_analysees'));
        $this->assertSame(0, $response->json('marge_estimee.lignes_sans_cout_estime'));
    }

    public function test_activity_flow_returns_the_expected_structure(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/flow-analytics/activity-flow', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'period', 'range', 'actions_par_module', 'actions_par_utilisateur', 'actions_par_type', 'connexion',
            ]);
    }

    // Goulot d'etranglement (Doc/analyse_flux_modele_donnees.md, §3) : un seuil de 1 jour
    // sur BROUILLON, une commande y reste 5 jours -> doit remonter dans /bottlenecks.
    public function test_bottlenecks_flags_a_stage_beyond_its_threshold(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        FlowStageThreshold::query()->create([
            'flow_type' => FlowType::VENTE->value,
            'stage_code' => 'BROUILLON',
            'label' => 'Test seuil brouillon',
            'threshold_type' => FlowThresholdType::DUREE_JOURS->value,
            'threshold_value' => 1,
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $order = SalesOrder::factory()->create(['client_id' => $client->id, 'currency_id' => $currency->id]);
        $order->statusHistory()->create(['from_status' => null, 'to_status' => SalesOrderStatus::BROUILLON->value, 'changed_by_user_id' => $admin->id, 'created_at' => '2020-05-01 09:00:00']);
        $order->statusHistory()->create(['from_status' => SalesOrderStatus::BROUILLON->value, 'to_status' => SalesOrderStatus::PROFORMA_ENVOYEE->value, 'changed_by_user_id' => $admin->id, 'created_at' => '2020-05-06 09:00:00']);

        $response = $this->getJson('/api/flow-analytics/bottlenecks?period=month&date=2020-05-15', $headers)->assertOk();

        $goulots = collect($response->json('goulots'));
        $this->assertTrue($goulots->contains(fn ($g) => $g['flow_type'] === 'VENTE' && $g['stage_code'] === 'BROUILLON'));
    }

    public function test_export_csv_returns_a_csv_file(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->get('/api/flow-analytics/sales-flow/export?format=csv', $headers);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_requires_a_known_flow(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->get('/api/flow-analytics/inconnu/export?format=csv', $headers)->assertNotFound();
    }
}
