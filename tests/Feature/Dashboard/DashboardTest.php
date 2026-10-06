<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentMethod;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\Currency;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Tableau de bord et KPI temps reel (cahier des charges NJ Global Trade v2, section 2.3),
 * App\Http\Controllers\Dashboard\DashboardController.
 */
class DashboardTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_view_dashboard_stats(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }

    public function test_admin_without_dashboard_permission_is_forbidden(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('dashboard.view');

        $this->getJson('/api/dashboard/stats', $headers)->assertForbidden();
    }

    public function test_dashboard_stats_returns_the_four_kpi_sections(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/dashboard/stats', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'period', 'range' => ['from', 'to'],
                'revenue' => ['ca_encaisse', 'commission_realisee', 'nombre_encaissements', 'nombre_remboursements', 'commandes_actives' => ['count', 'montant_total', 'commission_totale']],
                'factures_en_attente' => ['count', 'en_retard', 'proche_echeance'],
                'taux_transformation' => ['commandes_non_annulees', 'commandes_payees', 'taux_pourcentage'],
                'performance_par_provenance',
            ]);
    }

    // La migration 2026_08_25_000001_seed_default_client_categories_table doit avoir seede
    // les 3 provenances du cahier des charges §2.5 pour que ce test ait des categories a
    // rattacher.
    public function test_default_client_categories_are_seeded_for_the_provenance_kpi(): void
    {
        $this->assertDatabaseHas('client_categories', ['code' => 'ECOM_RICH', 'label' => 'Ecom-Rich']);
        $this->assertDatabaseHas('client_categories', ['code' => 'DIRECT', 'label' => 'Client direct']);
        $this->assertDatabaseHas('client_categories', ['code' => 'AUTRE', 'label' => 'Autre']);
    }

    // KPI "CA et commission" (section 2.3) : calcule sur les encaissements reellement recus
    // dans la periode demandee (voir la note de tete de DashboardController), pas sur le
    // montant des commandes creees. Un paiement hors periode ou rattache a une commande
    // annulee ne doit pas compter ; un remboursement dans la periode doit venir en deduction.
    public function test_ca_encaisse_only_counts_payments_within_the_period_on_non_cancelled_orders(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $orderInPeriod = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'order_date' => '2020-05-10',
            'total_amount' => 100000,
            'commission_amount' => 10000,
        ]);
        $orderInPeriod->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 100000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::ORANGE_MONEY->value,
            'receipt_number' => 'REC-TEST-1',
            'paid_at' => '2020-05-12 10:00:00',
        ]);
        $orderInPeriod->payments()->create([
            'direction' => PaymentDirection::REMBOURSEMENT->value,
            'amount' => 15000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::ORANGE_MONEY->value,
            'receipt_number' => 'REMB-TEST-1',
            'paid_at' => '2020-05-20 10:00:00',
        ]);

        $orderOutsidePeriod = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'order_date' => '2020-04-01',
            'total_amount' => 40000,
        ]);
        $orderOutsidePeriod->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 40000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::ESPECES->value,
            'receipt_number' => 'REC-TEST-2',
            'paid_at' => '2020-04-15 10:00:00',
        ]);

        $cancelledOrder = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::ANNULEE->value,
            'order_date' => '2020-05-05',
            'total_amount' => 70000,
        ]);
        $cancelledOrder->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 70000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::WAVE->value,
            'receipt_number' => 'REC-TEST-3',
            'paid_at' => '2020-05-06 10:00:00',
        ]);

        $response = $this->getJson('/api/dashboard/stats?period=month&date=2020-05-15', $headers)->assertOk();

        $response->assertJsonPath('revenue.ca_encaisse', 85000.0);
        $response->assertJsonPath('revenue.nombre_encaissements', 1);
        $response->assertJsonPath('revenue.nombre_remboursements', 1);
    }

    // KPI "Taux de transformation" et "Performance par provenance" (section 2.3), sur les
    // commandes creees dans la periode.
    public function test_conversion_rate_and_performance_by_provenance(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $currency = Currency::factory()->create();
        $ecomRich = ClientCategory::query()->where('code', 'ECOM_RICH')->firstOrFail();
        $direct = ClientCategory::query()->where('code', 'DIRECT')->firstOrFail();

        $ecomRichClient = Client::factory()->create(['category_id' => $ecomRich->id]);
        $directClient = Client::factory()->create(['category_id' => $direct->id]);

        SalesOrder::factory()->create([
            'client_id' => $ecomRichClient->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::PAYEE->value,
            'order_date' => '2020-05-03',
            'total_amount' => 50000,
        ]);
        SalesOrder::factory()->create([
            'client_id' => $directClient->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'order_date' => '2020-05-20',
            'total_amount' => 30000,
        ]);
        // Commande annulee, exclue du taux de transformation comme de la repartition.
        SalesOrder::factory()->create([
            'client_id' => $directClient->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::ANNULEE->value,
            'order_date' => '2020-05-10',
            'total_amount' => 999999,
        ]);

        $response = $this->getJson('/api/dashboard/stats?period=month&date=2020-05-15', $headers)->assertOk();

        $response->assertJsonPath('taux_transformation.commandes_non_annulees', 2);
        $response->assertJsonPath('taux_transformation.commandes_payees', 1);
        $response->assertJsonPath('taux_transformation.taux_pourcentage', 50.0);

        $provenance = collect($response->json('performance_par_provenance'));
        $ecomRichRow = $provenance->firstWhere('category_code', 'ECOM_RICH');
        $directRow = $provenance->firstWhere('category_code', 'DIRECT');

        $this->assertNotNull($ecomRichRow);
        $this->assertSame(1, $ecomRichRow['commandes_count']);
        $this->assertEquals(50000.0, $ecomRichRow['ca_total']);

        $this->assertNotNull($directRow);
        $this->assertSame(1, $directRow['commandes_count']);
        $this->assertEquals(30000.0, $directRow['ca_total']);
    }

    // KPI "Factures en attente" + signal de relance (sections 2.3 et 2.4) : la liste ne
    // doit garder que les commandes non annulees et pas encore integralement payees, avec
    // le bon niveau d'alerte selon l'echeance valid_until.
    public function test_pending_sales_orders_lists_unpaid_orders_with_alert_levels(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $today = now()->startOfDay();

        $overdue = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => $today->copy()->subDays(3)->toDateString(),
        ]);
        $soon = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::PARTIELLEMENT_PAYEE->value,
            'valid_until' => $today->copy()->addDay()->toDateString(),
        ]);
        $safe = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => $today->copy()->addDays(10)->toDateString(),
        ]);
        // Payee : ne doit pas apparaitre dans la liste.
        SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::PAYEE->value,
            'valid_until' => $today->copy()->subDays(1)->toDateString(),
        ]);
        // Annulee : ne doit pas apparaitre non plus, malgre une echeance depassee.
        SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::ANNULEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => $today->copy()->subDays(5)->toDateString(),
        ]);

        $response = $this->getJson('/api/dashboard/pending-sales-orders', $headers)->assertOk();

        $this->assertSame(3, $response->json('meta.total'));

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame('DEPASSEE', $byId[$overdue->id]['niveau_alerte']);
        $this->assertSame('PROCHE', $byId[$soon->id]['niveau_alerte']);
        $this->assertNull($byId[$safe->id]['niveau_alerte']);
    }
}
