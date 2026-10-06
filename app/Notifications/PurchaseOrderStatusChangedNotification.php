<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;

// Evenement #3 du module (Doc/notifications_modele_donnees.md, §5) : changement de statut
// d'une commande fournisseur. Declenchee depuis
// Http\Controllers\Supplier\PurchaseOrderController::update(), au meme endroit que l'ecriture
// de purchase_order_status_history (module Analyse des flux) — meme detection de changement
// effectif, pas de duplication de logique.
class PurchaseOrderStatusChangedNotification extends BaseAppNotification
{
    private const IMPORTANT_STATUSES = [PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::CANCELLED];

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
        private readonly ?PurchaseOrderStatus $oldStatus,
        private readonly PurchaseOrderStatus $newStatus,
    ) {}

    public function type(): string
    {
        return 'purchase_order.status_changed';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::ACHAT;
    }

    public function priority(): NotificationPriority
    {
        return in_array($this->newStatus, self::IMPORTANT_STATUSES, true)
            ? NotificationPriority::IMPORTANT
            : NotificationPriority::INFO;
    }

    public function title(object $notifiable): string
    {
        return 'Commande fournisseur '.$this->purchaseOrder->reference.' — '.$this->newStatus->value;
    }

    public function body(object $notifiable): string
    {
        return sprintf(
            'La commande fournisseur %s est passee de %s a %s.',
            $this->purchaseOrder->reference,
            $this->oldStatus?->value ?? '—',
            $this->newStatus->value,
        );
    }

    public function data(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'reference' => $this->purchaseOrder->reference,
            'from_status' => $this->oldStatus?->value,
            'to_status' => $this->newStatus->value,
            'link' => '/purchase-orders/'.$this->purchaseOrder->id,
        ];
    }

    public function relatedEntityType(): ?string
    {
        return 'PurchaseOrder';
    }

    public function relatedEntityId(): int|string|null
    {
        return $this->purchaseOrder->id;
    }
}
