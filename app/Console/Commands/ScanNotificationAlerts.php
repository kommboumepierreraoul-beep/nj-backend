<?php

namespace App\Console\Commands;

use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\FlowAnalytics\FlowAnalyticsController;
use App\Models\Notification;
use App\Models\SalesOrder;
use App\Notifications\FlowThresholdBreachedNotification;
use App\Notifications\SalesOrderPendingAlertNotification;
use App\Notifications\SecurityAnomalyDetectedNotification;
use App\Support\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Commande planifiee du module Notifications (Doc/notifications_modele_donnees.md, decisions
 * §2.8/§2.9), quotidienne (voir routes/console.php). Transforme en notification poussee 3
 * signaux aujourd'hui calcules uniquement a la demande : relances de facture (meme seuil que
 * DashboardController), goulots de flux et anomalies de securite (memes seuils que
 * FlowAnalyticsController/flow_stage_thresholds). Chaque scan est encadre individuellement :
 * l'echec d'un scan n'empeche pas les autres de s'executer. Idempotent (decision §2.9) : au
 * plus une notification par situation et par jour.
 */
class ScanNotificationAlerts extends Command
{
    protected $signature = 'notifications:scan-alerts';

    protected $description = "Detecte les factures en attente proches/depassees, les goulots de flux et les anomalies de securite, et pousse une notification interne (une fois par jour et par situation).";

    public function handle(FlowAnalyticsController $flowAnalyticsController): int
    {
        $this->safelyRun('relances de facture', fn () => $this->scanPendingInvoices());
        $this->safelyRun('goulots de flux et anomalies de securite', fn () => $this->scanFlowAndSecurityBreaches($flowAnalyticsController));

        return self::SUCCESS;
    }

    private function safelyRun(string $label, callable $scan): void
    {
        try {
            $scan();
        } catch (Throwable $exception) {
            $this->error("Scan \"{$label}\" en echec : {$exception->getMessage()}");
        }
    }

    /**
     * Evenement #5 (Doc/notifications_modele_donnees.md, §5) : meme seuil que
     * DashboardController::RELANCE_PROCHE_SEUIL_JOURS, reutilise tel quel (pas de 2e valeur).
     */
    private function scanPendingInvoices(): void
    {
        $today = Carbon::today();
        $procheEcheanceLimite = $today->copy()->addDays(DashboardController::RELANCE_PROCHE_SEUIL_JOURS);

        $pendingOrders = SalesOrder::query()
            ->with('createdBy')
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->where('payment_status', '!=', SalesOrderPaymentStatus::PAYEE->value)
            ->whereNotNull('valid_until')
            ->get();

        foreach ($pendingOrders as $order) {
            $niveauAlerte = match (true) {
                $order->valid_until->lt($today) => 'DEPASSEE',
                $order->valid_until->lte($procheEcheanceLimite) => 'PROCHE',
                default => null,
            };

            if ($niveauAlerte === null || $this->alreadyNotifiedToday(
                'sales_order.pending_alert', 'SalesOrder', $order->id, ['niveau_alerte' => $niveauAlerte],
            )) {
                continue;
            }

            // "DEPASSEE" est aussi remonte a tous les ADMIN/SUPER_ADMIN, pas seulement au
            // createur (Doc/notifications_modele_donnees.md, §5, note sur les destinataires) ;
            // sans createur connu, repli sur les ADMIN/SUPER_ADMIN dans tous les cas (§5, "Repli
            // si le destinataire relationnel est null"). dedupRecipients() evite un doublon si
            // le createur est lui-meme ADMIN/SUPER_ADMIN.
            $recipients = ($niveauAlerte === 'DEPASSEE' || ! $order->createdBy)
                ? NotificationDispatcher::dedupRecipients($order->createdBy, NotificationDispatcher::activeAdmins())
                : NotificationDispatcher::dedupRecipients($order->createdBy);

            NotificationDispatcher::notify($recipients, new SalesOrderPendingAlertNotification($order, $niveauAlerte));
        }
    }

    /**
     * Evenements #6 et #7 (Doc/notifications_modele_donnees.md, §5) : une seule detection
     * (FlowAnalyticsController::detectBreaches(), qui reutilise buildBottlenecks() sans le
     * dupliquer) sur une fenetre glissante de 7 jours, ventilee en notification FLUX
     * (ACHAT/VENTE) ou SECURITE (ACTIVITE) selon le flow_type du goulot.
     */
    private function scanFlowAndSecurityBreaches(FlowAnalyticsController $flowAnalyticsController): void
    {
        $to = Carbon::now();
        $from = $to->copy()->subDays(7);

        foreach ($flowAnalyticsController->detectBreaches($from, $to) as $goulot) {
            if ($goulot['flow_type'] === 'ACTIVITE') {
                if ($this->alreadyNotifiedToday(
                    'security.anomaly_detected', 'SystemTrace', null, ['event_code' => $goulot['stage_code']],
                )) {
                    continue;
                }

                NotificationDispatcher::notifyAdmins(new SecurityAnomalyDetectedNotification(
                    $goulot['stage_code'],
                    (int) $goulot['valeur_observee'],
                    $goulot['seuil'],
                ));

                continue;
            }

            if ($this->alreadyNotifiedToday(
                'flow_analytics.threshold_breached', 'FlowStageThreshold', null,
                ['flow_type' => $goulot['flow_type'], 'stage_code' => $goulot['stage_code']],
            )) {
                continue;
            }

            NotificationDispatcher::notifyAdmins(new FlowThresholdBreachedNotification($goulot));
        }
    }

    /**
     * Deduplication quotidienne (decision §2.9) : au plus une notification par type + entite
     * liee (si applicable) + valeurs de $matchData retrouvees telles quelles dans data, sur la
     * journee courante — evite de renvoyer la meme alerte chaque jour tant que la situation
     * n'est pas resolue (voir Doc/notifications_modele_donnees.md, §2, decision 9).
     */
    private function alreadyNotifiedToday(string $type, string $relatedEntityType, ?int $relatedEntityId, array $matchData): bool
    {
        return Notification::query()
            ->where('type', $type)
            ->where('related_entity_type', $relatedEntityType)
            ->when($relatedEntityId !== null, fn ($query) => $query->where('related_entity_id', $relatedEntityId))
            ->whereDate('created_at', Carbon::today())
            ->get()
            ->contains(function (Notification $notification) use ($matchData) {
                $data = $notification->data ?? [];

                foreach ($matchData as $key => $value) {
                    if (($data[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            });
    }
}
