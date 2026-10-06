<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\SalesOrderPayment;

// Evenement #4 du module (Doc/notifications_modele_donnees.md, §5) : un encaissement vient
// d'etre enregistre sur une commande client. Declenchee depuis
// Http\Controllers\SalesOrder\SalesOrderPaymentController::createPayment(), uniquement pour
// direction=ENCAISSEMENT (un remboursement n'est pas un encaissement a feter).
class SalesOrderPaymentReceivedNotification extends BaseAppNotification
{
    public function __construct(private readonly SalesOrderPayment $payment) {}

    public function type(): string
    {
        return 'sales_order_payment.received';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::PAIEMENT;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::IMPORTANT;
    }

    public function title(object $notifiable): string
    {
        $reference = $this->payment->salesOrder->reference ?? 'commande #'.$this->payment->sales_order_id;

        return 'Paiement recu — '.$reference;
    }

    public function body(object $notifiable): string
    {
        $reference = $this->payment->salesOrder->reference ?? 'commande #'.$this->payment->sales_order_id;
        $currency = $this->payment->currency;

        return sprintf(
            'Un encaissement de %s %s a ete enregistre sur %s.',
            number_format((float) $this->payment->amount, 2),
            $currency->code ?? '',
            $reference,
        );
    }

    public function data(object $notifiable): array
    {
        return [
            'sales_order_id' => $this->payment->sales_order_id,
            'sales_order_payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'link' => '/sales-orders/'.$this->payment->sales_order_id,
        ];
    }

    public function relatedEntityType(): ?string
    {
        return 'SalesOrder';
    }

    public function relatedEntityId(): int|string|null
    {
        return $this->payment->sales_order_id;
    }
}
