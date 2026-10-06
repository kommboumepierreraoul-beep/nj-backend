<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;

// Evenement #6 du module (Doc/notifications_modele_donnees.md, §5) : version poussee de la
// detection de goulot d'etranglement du module Analyse des flux
// (FlowAnalyticsController::detectBreaches(), qui reutilise buildBottlenecks() sans le
// dupliquer). Declenchee par App\Console\Commands\ScanNotificationAlerts. $goulot est une
// entree du tableau retourne par detectBreaches() (forme : flow_type, stage_code, label,
// valeur_observee, seuil, ecart, unite — voir FlowAnalyticsController::bottleneckRow()).
class FlowThresholdBreachedNotification extends BaseAppNotification
{
    /**
     * @param  array{flow_type: string, stage_code: string, label: string, valeur_observee: float, seuil: float, ecart: float, unite: string}  $goulot
     */
    public function __construct(private readonly array $goulot) {}

    public function type(): string
    {
        return 'flow_analytics.threshold_breached';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::FLUX;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::IMPORTANT;
    }

    public function title(object $notifiable): string
    {
        return 'Goulot detecte — '.$this->goulot['label'];
    }

    public function body(object $notifiable): string
    {
        return sprintf(
            '%s : %s %s observe(s), seuil %s %s depasse (ecart %s).',
            $this->goulot['label'],
            $this->goulot['valeur_observee'],
            $this->goulot['unite'],
            $this->goulot['seuil'],
            $this->goulot['unite'],
            $this->goulot['ecart'],
        );
    }

    public function data(object $notifiable): array
    {
        return $this->goulot + ['link' => '/flow-analytics'];
    }

    public function relatedEntityType(): ?string
    {
        return 'FlowStageThreshold';
    }
}
