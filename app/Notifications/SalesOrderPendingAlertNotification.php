<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\SalesOrder;

// Evenement #5 du module (Doc/notifications_modele_donnees.md, §5) : version poussee de
// l'alerte de relance deja affichee sur le Dashboard (niveau_alerte, cahier des charges §2.4).
// Declenchee par la commande planifiee App\Console\Commands\ScanNotificationAlerts, jamais
// depuis une requete HTTP. Rappel important (cahier des charges §2.4) : cette notification
// informe l'EQUIPE INTERNE qu'une relance client est a faire, elle ne relance jamais le
// client elle-meme.
class SalesOrderPendingAlertNotification extends BaseAppNotification
{
    /**
     * @param  'DEPASSEE'|'PROCHE'  $niveauAlerte
     */
    public function __construct(
        private readonly SalesOrder $salesOrder,
        private readonly string $niveauAlerte,
    ) {}

    public function type(): string
    {
        return 'sales_order.pending_alert';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::RELANCE;
    }

    public function priority(): NotificationPriority
    {
        return $this->niveauAlerte === 'DEPASSEE' ? NotificationPriority::CRITIQUE : NotificationPriority::IMPORTANT;
    }

    public function title(object $notifiable): string
    {
        return $this->niveauAlerte === 'DEPASSEE'
            ? 'Facture en retard — '.$this->salesOrder->reference
            : 'Facture proche echeance — '.$this->salesOrder->reference;
    }

    public function body(object $notifiable): string
    {
        $client = $this->salesOrder->client->full_name ?? 'client non precise';

        return $this->niveauAlerte === 'DEPASSEE'
            ? sprintf('La commande %s (%s) a depasse son echeance de paiement. Relance manuelle a faire.', $this->salesOrder->reference, $client)
            : sprintf('La commande %s (%s) approche de son echeance de paiement. Pensez a relancer.', $this->salesOrder->reference, $client);
    }

    public function data(object $notifiable): array
    {
        return [
            'sales_order_id' => $this->salesOrder->id,
            'reference' => $this->salesOrder->reference,
            'niveau_alerte' => $this->niveauAlerte,
            'valid_until' => $this->salesOrder->valid_until?->toDateString(),
            'link' => '/dashboard/factures-en-attente',
        ];
    }

    public function relatedEntityType(): ?string
    {
        return 'SalesOrder';
    }

    public function relatedEntityId(): int|string|null
    {
        return $this->salesOrder->id;
    }
}
