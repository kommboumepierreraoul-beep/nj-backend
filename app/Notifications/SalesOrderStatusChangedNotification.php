<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;

// Evenement #2 du module (Doc/notifications_modele_donnees.md, §5) : changement de statut
// d'une commande client. Declenchee depuis
// Http\Controllers\SalesOrder\SalesOrderController::updateStatus().
class SalesOrderStatusChangedNotification extends BaseAppNotification
{
    public function __construct(
        private readonly SalesOrder $salesOrder,
        private readonly ?SalesOrderStatus $oldStatus,
        private readonly SalesOrderStatus $newStatus,
    ) {}

    public function type(): string
    {
        return 'sales_order.status_changed';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::COMMANDE;
    }

    public function priority(): NotificationPriority
    {
        return $this->newStatus === SalesOrderStatus::ANNULEE
            ? NotificationPriority::CRITIQUE
            : NotificationPriority::IMPORTANT;
    }

    public function title(object $notifiable): string
    {
        return 'Commande '.$this->salesOrder->reference.' — '.$this->newStatus->value;
    }

    public function body(object $notifiable): string
    {
        if ($this->newStatus === SalesOrderStatus::ANNULEE) {
            return sprintf(
                'La commande %s a ete annulee (motif : %s).',
                $this->salesOrder->reference,
                $this->salesOrder->cancellation_reason ?? 'non precise',
            );
        }

        return sprintf(
            'La commande %s est passee de %s a %s.',
            $this->salesOrder->reference,
            $this->oldStatus?->value ?? '—',
            $this->newStatus->value,
        );
    }

    public function data(object $notifiable): array
    {
        return [
            'sales_order_id' => $this->salesOrder->id,
            'reference' => $this->salesOrder->reference,
            'from_status' => $this->oldStatus?->value,
            'to_status' => $this->newStatus->value,
            'link' => '/sales-orders/'.$this->salesOrder->id,
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
