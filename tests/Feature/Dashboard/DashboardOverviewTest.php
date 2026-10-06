<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentMethod;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Blocs complementaires du tableau de bord (NJ Global Trade Dashboard.dc.html, rangees 2 a
 * 5 : performance mensuelle, qualite du recouvrement, velocite du cycle de vie, panneaux
 * modules, fils d'activite) — GET /dashboard/overview,
 * App\Http\Controllers\Dashboard\Concerns\BuildsDashboardOverview.
 */
class DashboardOverviewTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_view_dashboard_overview(): void
    {
        $this->getJson('/api/dashboard/overview')->assertUnauthorized();
    }

    public function test_admin_without_dashboard_permission_is_forbidden(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('dashboard.view');

        $this->getJson('/api/dashboard/overview', $headers)->assertForbidden();
    }

    public function test_overview_returns_every_block(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/dashboard/overview', $headers)
            ->assertOk()
            ->assertJsonStructure([
                'performance_mensuelle' => [
                    'mois' => [['mois', 'ca_engage', 'net_encaisse']],
                    'resume' => ['engage_6m', 'encaisse_6m', 'meilleur_mois', 'conversion_caisse_pourcentage'],
                ],
                'qualite_recouvrement' => [
                    'ca_engage_total', 'net_encaisse_total', 'credite_avoir_total', 'taux_recouvre_pourcentage',
                    'buckets' => [['payment_status', 'count', 'montant', 'part_pourcentage']],
                ],
                'velocite_cycle_vie' => ['total_jours', 'etapes'],
                'panels' => [['code', 'metriques', 'barres']],
                'feeds' => ['derniers_mouvements', 'derniers_documents'],
                'relances_count',
            ]);
    }

    public function test_overview_exposes_the_eight_module_panels_in_order(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $codes = collect($this->getJson('/api/dashboard/overview', $headers)->assertOk()->json('panels'))
            ->pluck('code')
            ->all();

        $this->assertSame([
            'pipeline_commercial', 'tresorerie', 'documents_emis', 'portefeuille_clients',
            'catalogue', 'sourcing_fournisseurs', 'acces_tracabilite', 'configuration',
        ], $codes);
    }

    public function test_monthly_performance_buckets_engaged_and_collected_by_month(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $thisMonth = Carbon::today()->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonth();

        $order = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'order_date' => $lastMonth->copy()->addDays(3)->toDateString(),
            'total_amount' => 200000,
        ]);
        $order->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 120000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::WAVE->value,
            'receipt_number' => 'REC-OVW-1',
            'paid_at' => $lastMonth->copy()->addDays(10)->toDateTimeString(),
        ]);
        $order->payments()->create([
            'direction' => PaymentDirection::ENCAISSEMENT->value,
            'amount' => 50000,
            'currency_id' => $currency->id,
            'payment_method' => SalesOrderPaymentMethod::WAVE->value,
            'receipt_number' => 'REC-OVW-2',
            'paid_at' => $thisMonth->copy()->addDays(2)->toDateTimeString(),
        ]);

        $months = collect($this->getJson('/api/dashboard/overview', $headers)->assertOk()->json('performance_mensuelle.mois'))
            ->keyBy('mois');

        $this->assertEquals(200000, $months[$lastMonth->format('Y-m')]['ca_engage']);
        $this->assertEquals(120000, $months[$lastMonth->format('Y-m')]['net_encaisse']);
        $this->assertEquals(0, $months[$thisMonth->format('Y-m')]['ca_engage']);
        $this->assertEquals(50000, $months[$thisMonth->format('Y-m')]['net_encaisse']);
    }

    public function test_recovery_quality_splits_engaged_revenue_by_payment_status(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        SalesOrder::factory()->create([
            'client_id' => $client->id, 'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::PAYEE->value, 'total_amount' => 60000,
        ]);
        SalesOrder::factory()->create([
            'client_id' => $client->id, 'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value, 'total_amount' => 40000,
        ]);
        // Annulee : exclue du recouvrement.
        SalesOrder::factory()->create([
            'client_id' => $client->id, 'currency_id' => $currency->id,
            'status' => SalesOrderStatus::ANNULEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value, 'total_amount' => 999999,
        ]);

        $recovery = $this->getJson('/api/dashboard/overview', $headers)->assertOk()->json('qualite_recouvrement');
        $buckets = collect($recovery['buckets'])->keyBy('payment_status');

        $this->assertEquals(100000, $recovery['ca_engage_total']);
        $this->assertEquals(1, $buckets['PAYEE']['count']);
        $this->assertEquals(60000, $buckets['PAYEE']['montant']);
        $this->assertEquals(40000, $buckets['NON_PAYEE']['montant']);
    }

    public function test_lifecycle_velocity_measures_days_between_status_transitions(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $order = SalesOrder::factory()->create([
            'client_id' => $client->id, 'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
        ]);

        // Insertion directe : sales_order_status_history est un journal immuable sans
        // timestamps Eloquent, created_at n'est pas mass-assignable via ->create().
        \Illuminate\Support\Facades\DB::table('sales_order_status_history')->insert([
            [
                'sales_order_id' => $order->id, 'from_status' => null,
                'to_status' => SalesOrderStatus::BROUILLON->value,
                'changed_by_user_id' => $admin->id, 'created_at' => '2026-01-01 09:00:00',
            ],
            [
                'sales_order_id' => $order->id, 'from_status' => SalesOrderStatus::BROUILLON->value,
                'to_status' => SalesOrderStatus::PROFORMA_ENVOYEE->value,
                'changed_by_user_id' => $admin->id, 'created_at' => '2026-01-04 09:00:00',
            ],
        ]);

        $velocity = $this->getJson('/api/dashboard/overview', $headers)->assertOk()->json('velocite_cycle_vie');
        $stage = collect($velocity['etapes'])->firstWhere('to_status', SalesOrderStatus::PROFORMA_ENVOYEE->value);

        $this->assertNotNull($stage);
        $this->assertEquals('BROUILLON', $stage['from_status']);
        $this->assertEquals(3, $stage['jours_moyen']);
        $this->assertEquals(1, $stage['commandes_count']);
    }
}
