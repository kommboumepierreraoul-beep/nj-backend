<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->sales_order_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'currency_id' => $this->currency_id,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'external_reference' => $this->external_reference,
            'receipt_number' => $this->receipt_number,
            'is_voided' => $this->is_voided,
            'voided_reason' => $this->voided_reason,
            'voided_at' => $this->voided_at,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            // Correctif : jusqu'ici absent, alors que le frontend (SalesOrderPaymentsTab)
            // affiche `row.currency.code` depuis cette même ressource — seul `currency_id`
            // était renvoyé. Cf. SalesOrderResource::toArray() qui expose déjà `currency`
            // de la même façon pour la commande elle-même.
            'currency' => $this->whenLoaded('currency'),
            // Contexte commande/client, uniquement quand explicitement charge (registre
            // transverse des paiements, SalesOrderPaymentController::indexGlobal()) — absent
            // partout ailleurs, ou cette ressource est deja utilisee dans le contexte d'une
            // commande precise et cette information serait redondante.
            'sales_order' => $this->whenLoaded('salesOrder', fn () => [
                'id' => $this->salesOrder->id,
                'reference' => $this->salesOrder->reference,
                'client' => $this->salesOrder->relationLoaded('client') && $this->salesOrder->client ? [
                    'id' => $this->salesOrder->client->id,
                    'full_name' => $this->salesOrder->client->full_name,
                ] : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
