<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\RfqSupplierQuote;

// Evenement #1 du module (Doc/notifications_modele_donnees.md, §5) : un fournisseur a
// repondu a une ligne de RFQ avec un devis. Declenchee depuis
// Http\Controllers\Supplier\RfqSupplierQuoteController::store().
class RfqSupplierQuoteReceivedNotification extends BaseAppNotification
{
    public function __construct(private readonly RfqSupplierQuote $quote) {}

    public function type(): string
    {
        return 'rfq_supplier_quote.received';
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::ACHAT;
    }

    public function priority(): NotificationPriority
    {
        return NotificationPriority::INFO;
    }

    public function title(object $notifiable): string
    {
        $rfq = $this->quote->rfqSupplier->rfq;

        return 'Nouveau devis fournisseur — '.($rfq->reference ?? 'RFQ #'.$rfq->id);
    }

    public function body(object $notifiable): string
    {
        $supplier = $this->quote->rfqSupplier->supplier;
        $currency = $this->quote->currency;

        return sprintf(
            '%s a transmis un devis a %s %s pour la ligne #%d.',
            $supplier->company_name ?? 'Un fournisseur',
            number_format((float) $this->quote->quoted_unit_price, 2),
            $currency->code ?? '',
            $this->quote->rfq_item_id,
        );
    }

    public function data(object $notifiable): array
    {
        $rfq = $this->quote->rfqSupplier->rfq;

        return [
            'rfq_id' => $rfq->id,
            'rfq_reference' => $rfq->reference,
            'supplier_id' => $this->quote->rfqSupplier->supplier_id,
            'link' => '/rfqs/'.$rfq->id,
        ];
    }

    public function relatedEntityType(): ?string
    {
        return 'RfqSupplierQuote';
    }

    public function relatedEntityId(): int|string|null
    {
        return $this->quote->id;
    }
}
