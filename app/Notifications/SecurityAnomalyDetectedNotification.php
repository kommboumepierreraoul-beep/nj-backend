<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;

// Evenement #7 du module (Doc/notifications_modele_donnees.md, §5) : pic d'echecs de
// connexion/permissions refusees sur system_traces, au-dela du seuil flow_stage_thresholds
// (flow_type=ACTIVITE) deja utilise par le module Analyse des flux — pas de 2e seuil
// duplique. Declenchee par App\Console\Commands\ScanNotificationAlerts. Formule comme un
// signal de calibration de permission plutot qu'une alerte de securite avancee, meme
// prudence que Doc/analyse_flux_modele_donnees.md §1.4 ("a formuler comme tel cote
// restitution pour ne pas alarmer a tort").
class SecurityAnomalyDetectedNotification extends BaseAppNotification
{
    public function __construct(
        private readonly string $eventCode,
        private readonly int $observedCount,
        private readonly float $threshold,
    ) {}

    public function type(): string
    {
        return 'security.anomaly_detected';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::SECURITE;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::CRITIQUE;
    }

    public function title(object $notifiable): string
    {
        return 'Activite inhabituelle detectee';
    }

    public function body(object $notifiable): string
    {
        $label = match ($this->eventCode) {
            'AUTH_LOGIN_FAILED' => "d'echecs de connexion",
            'AUTH_PERMISSION_DENIED' => "d'acces refuses par permission",
            default => "d'evenements ".$this->eventCode,
        };

        return sprintf(
            '%d occurrences %s aujourd\'hui (seuil configure : %s) — a verifier, pas necessairement une intrusion.',
            $this->observedCount,
            $label,
            $this->threshold,
        );
    }

    public function data(object $notifiable): array
    {
        return [
            'event_code' => $this->eventCode,
            'observed_count' => $this->observedCount,
            'threshold' => $this->threshold,
            'link' => '/flow-analytics',
        ];
    }

    public function relatedEntityType(): ?string
    {
        return 'SystemTrace';
    }
}
