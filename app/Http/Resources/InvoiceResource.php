<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'sales_order_id' => $this->sales_order_id,
            'document_type' => $this->document_type?->value ?? $this->document_type,
            'version' => $this->version,
            'status' => $this->status?->value ?? $this->status,
            'supersedes_invoice_id' => $this->supersedes_invoice_id,
            'credits_invoice_id' => $this->credits_invoice_id,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'client_address' => $this->client_address,
            'client_tax_id' => $this->client_tax_id,
            'currency_id' => $this->currency_id,
            'language' => $this->language?->value ?? $this->language,
            'billing_mode' => $this->billing_mode?->value ?? $this->billing_mode,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'commission_amount' => $this->commission_amount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'transport_mode' => $this->transport_mode?->value ?? $this->transport_mode,
            'legal_mentions' => $this->legal_mentions,
            'due_date' => $this->due_date,
            'currency_equivalents' => $this->currency_equivalents,
            'proposal_details' => $this->proposal_details,
            'issued_at' => $this->issued_at,
            'sent_at' => $this->sent_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'issued_by_user_id' => $this->issued_by_user_id,
            'notes' => $this->notes,
            'client' => new ClientResource($this->whenLoaded('client')),
            'currency' => $this->whenLoaded('currency'),
            'items' => $this->whenLoaded('items'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            // Correctif : absents jusqu'ici alors que Doc/spec_pages_factures.md § 1 exige
            // une colonne « Émetteur » (InvoiceHistoryTab affiche deja row.issued_by?.name)
            // et, pour un AVOIR, la ligne « Crédite : <invoice_number> » (row.credits) — ni
            // l'un ni l'autre n'etait jamais renvoye, seul le FK brut issued_by_user_id /
            // credits_invoice_id l'etait. Cf. Invoice::issuedBy()/credits().
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy ? [
                'id' => $this->issuedBy->id,
                'name' => $this->issuedBy->name,
            ] : null),
            'credits' => $this->whenLoaded('credits', fn () => $this->credits ? [
                'id' => $this->credits->id,
                'invoice_number' => $this->credits->invoice_number,
            ] : null),
            'pdf_url' => $this->whenLoaded('attachments', function () {
                $attachment = $this->attachments->firstWhere('is_primary', true) ?? $this->attachments->first();

                return $attachment ? \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->file_path) : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
