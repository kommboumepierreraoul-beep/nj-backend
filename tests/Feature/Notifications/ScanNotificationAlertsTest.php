<?php

namespace Tests\Feature\Notifications;

use App\Enums\FlowThresholdType;
use App\Enums\FlowType;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FlowStageThreshold;
use App\Models\Notification;
use App\Models\SalesOrder;
use App\Models\SystemTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Commande planifiee App\Console\Commands\ScanNotificationAlerts
 * (Doc/notifications_modele_donnees.md, decisions §2.8/§2.9).
 */
class ScanNotificationAlertsTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    // Evenement #5 (Doc/notifications_modele_donnees.md, §5) : reutilise
    // DashboardController::RELANCE_PROCHE_SEUIL_JOURS (2 jours par defaut).
    public function test_pending_sales_orders_produce_alerts_at_the_right_level(): void
    {
        Http::fake();

        [$creator] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $today = now()->startOfDay();

        $overdue = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => $today->copy()->subDays(3)->toDateString(),
            'created_by_user_id' => $creator->id,
        ]);
        $safe = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => $today->copy()->addDays(10)->toDateString(),
            'created_by_user_id' => $creator->id,
        ]);

        $this->artisan('notifications:scan-alerts')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'type' => 'sales_order.pending_alert',
            'related_entity_id' => $overdue->id,
            'notifiable_user_id' => $creator->id,
        ]);
        $this->assertSame(
            0,
            Notification::query()->where('type', 'sales_order.pending_alert')->where('related_entity_id', $safe->id)->count(),
        );
    }

    // Decision §2.9 : au plus une notification par situation et par jour -- un deuxieme
    // passage de la commande le meme jour ne doit pas dupliquer l'alerte deja emise.
    public function test_running_the_scan_twice_the_same_day_does_not_duplicate_the_alert(): void
    {
        Http::fake();

        [$creator] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();

        $overdue = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::CONFIRMEE->value,
            'payment_status' => SalesOrderPaymentStatus::NON_PAYEE->value,
            'valid_until' => now()->subDays(1)->toDateString(),
            'created_by_user_id' => $creator->id,
        ]);

        $this->artisan('notifications:scan-alerts')->assertExitCode(0);
        $this->artisan('notifications:scan-alerts')->assertExitCode(0);

        $this->assertSame(
            1,
            Notification::query()
                ->where('type', 'sales_order.pending_alert')
                ->where('related_entity_id', $overdue->id)
                ->where('notifiable_user_id', $creator->id)
                ->count(),
        );
    }

    // Evenement #7 (Doc/notifications_modele_donnees.md, §5) : meme seuil flow_stage_thresholds
    // (flow_type=ACTIVITE) que le module Analyse des flux, pas de seuil duplique.
    public function test_a_login_failure_spike_produces_a_security_notification_for_admins(): void
    {
        Http::fake();

        [$admin] = $this->actingAsAdmin();

        FlowStageThreshold::query()->create([
            'flow_type' => FlowType::ACTIVITE->value,
            'stage_code' => 'AUTH_LOGIN_FAILED',
            'label' => 'Echecs de connexion',
            'threshold_type' => FlowThresholdType::COMPTEUR->value,
            'threshold_value' => 1,
            'is_active' => true,
        ]);

        SystemTrace::query()->create(['event' => 'auth.login_failed', 'created_at' => now()]);
        SystemTrace::query()->create(['event' => 'auth.login_failed', 'created_at' => now()]);

        $this->artisan('notifications:scan-alerts')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'type' => 'security.anomaly_detected',
            'notifiable_user_id' => $admin->id,
        ]);
    }
}
