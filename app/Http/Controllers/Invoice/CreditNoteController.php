<?php

namespace App\Http\Controllers\Invoice;

use App\Enums\AttachmentType;
use App\Enums\DocumentLanguage;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\LocalizesDocument;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesOrder\Concerns\RecalculatesPaymentStatus;
use App\Http\Resources\InvoiceResource;
use App\Models\AuditLog;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emission d'un AVOIR (Doc/factures_modele_donnees.md, section 8 ; decision revue le
 * 2026-08-18 sortant l'AVOIR du perimetre "hors iteration" note dans la migration
 * 2026_08_17_000005_seed_invoice_and_company_permissions_table). Un avoir credite un
 * document PROFORMA ou FACTURE deja emis (invoices.credits_invoice_id, colonne deja
 * presente depuis 2026_08_17_000003) et reduit le montant restant du sur la commande via
 * sales_orders.credited_amount — sans jamais recalculer la commission (simplification
 * documentee : la commission d'origine reste due independamment d'un avoir partiel ou
 * total) et sans generer de remboursement automatique (un remboursement reste une action
 * distincte, voir SalesOrderPaymentController avec direction=REMBOURSEMENT).
 */
class CreditNoteController extends Controller
{
    use LocalizesDocument;
    use RecalculatesPaymentStatus;

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        if (! in_array($invoice->document_type, [InvoiceDocumentType::PROFORMA, InvoiceDocumentType::FACTURE], true)) {
            return response()->json([
                'message' => 'Un avoir ne peut etre emis que pour une proforma ou une facture, jamais pour un autre avoir.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! in_array($invoice->status, [InvoiceStatus::EMISE, InvoiceStatus::ENVOYEE], true)) {
            return response()->json([
                'message' => 'Seul un document actif (emis ou envoye) peut etre credite.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string'],
            // Langue du document : FR/EN. Absent → langue du document crédité.
            'language' => ['sometimes', 'nullable', Rule::in([DocumentLanguage::FR->value, DocumentLanguage::EN->value])],
        ]);

        $documentLanguage = $this->resolveDocumentLanguage($validated['language'] ?? null, $invoice->language);

        $invoice->load('items');
        $sourceItems = $invoice->items->keyBy('id');
        $selections = [];

        if (isset($validated['items'])) {
            foreach ($validated['items'] as $line) {
                $sourceItem = $sourceItems->get($line['invoice_item_id']);

                if (! $sourceItem) {
                    return response()->json([
                        'message' => "La ligne #{$line['invoice_item_id']} n'appartient pas au document credite.",
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $quantity = isset($line['quantity'])
                    ? min((float) $line['quantity'], (float) $sourceItem->quantity)
                    : (float) $sourceItem->quantity;

                $selections[] = [$sourceItem, $quantity];
            }
        } else {
            // Aucune ligne fournie : avoir total, copie integrale des lignes du document
            // credite (Doc/factures_modele_donnees.md, section 8, comportement par defaut).
            foreach ($sourceItems as $sourceItem) {
                $selections[] = [$sourceItem, (float) $sourceItem->quantity];
            }
        }

        if (! $selections) {
            return response()->json(['message' => 'Aucune ligne a crediter.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $companySettings = CompanySettings::current();
        $reason = $validated['reason'] ?? null;

        $creditNote = DB::transaction(function () use ($invoice, $selections, $reason, $request, $documentLanguage) {
            $lockedOrder = SalesOrder::query()->whereKey($invoice->sales_order_id)->lockForUpdate()->firstOrFail();

            $version = Invoice::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->where('document_type', InvoiceDocumentType::AVOIR->value)
                ->count() + 1;

            // TVA (Doc/tva_addendum.md) : un avoir rembourse aussi la TVA proportionnelle
            // qui avait ete facturee sur les lignes creditees (contrairement a la
            // commission, qui elle reste due). Taux repris du document credite.
            $taxRate = $invoice->tax_rate !== null ? (float) $invoice->tax_rate : 0.0;

            $subtotal = 0.0;
            $lines = [];
            foreach ($selections as [$sourceItem, $quantity]) {
                $unitPrice = (float) $sourceItem->unit_price;
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineSubtotal;
                $lines[] = [
                    'sales_order_item_id' => $sourceItem->sales_order_item_id,
                    'label' => $sourceItem->label,
                    'description' => $sourceItem->description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'tax_rate' => $taxRate > 0 ? $taxRate : null,
                    'tax_amount' => $taxRate > 0 ? round($lineSubtotal * $taxRate / 100, 2) : 0,
                    'subtotal' => $lineSubtotal,
                ];
            }
            $subtotal = round($subtotal, 2);
            $taxAmount = $taxRate > 0 ? round($subtotal * $taxRate / 100, 2) : 0.0;

            $creditNote = Invoice::query()->create([
                'invoice_number' => 'AVR-'.$lockedOrder->reference.'-'.$version,
                'sales_order_id' => $lockedOrder->id,
                'document_type' => InvoiceDocumentType::AVOIR->value,
                'version' => $version,
                'status' => InvoiceStatus::EMISE->value,
                'credits_invoice_id' => $invoice->id,
                // Snapshot depuis le document credite (et non depuis Client/SalesOrder) :
                // un avoir doit refleter les coordonnees telles qu'elles figuraient sur le
                // document au moment ou il a ete emis, meme si le client a change d'adresse
                // depuis.
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client_name,
                'client_address' => $invoice->client_address,
                'client_tax_id' => $invoice->client_tax_id,
                'currency_id' => $invoice->currency_id,
                // Fallback 'FR' defensif : la colonne invoices.language est NOT NULL avec
                // defaut 'FR' (2026_08_17_000003), $invoice->language?->value ne devrait
                // donc jamais etre null en pratique, mais une insertion explicite de null
                // violerait la contrainte plutot que de retomber sur le defaut de colonne.
                'language' => $documentLanguage->value,
                'billing_mode' => $invoice->billing_mode?->value,
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                // Decision documentee : jamais de recalcul de commission sur un avoir
                // (Doc/factures_modele_donnees.md, section 8) — la commission originale
                // reste due, l'avoir ne porte que sur le montant marchandise credite.
                'commission_amount' => null,
                'tax_rate' => $taxRate > 0 ? $taxRate : null,
                'tax_amount' => $taxAmount,
                'total_amount' => round($subtotal + $taxAmount, 2),
                'transport_mode' => $invoice->transport_mode?->value,
                'legal_mentions' => $this->buildAvoirLegalMentions($invoice, $documentLanguage),
                'due_date' => null,
                'currency_equivalents' => null,
                'issued_at' => now(),
                'issued_by_user_id' => $request->user()->id,
                'notes' => $reason,
            ]);

            $index = 0;
            foreach ($lines as $line) {
                $creditNote->items()->create($line + ['sort_order' => $index++]);
            }

            // credited_amount vient en deduction du montant restant du sur la commande,
            // dont total_amount inclut la TVA : on credite donc le total de l'avoir
            // (marchandise + TVA), sinon un avoir total ne solderait jamais la commande.
            $lockedOrder->forceFill(['credited_amount' => (float) $lockedOrder->credited_amount + round($subtotal + $taxAmount, 2)])->save();

            return $creditNote;
        });

        $salesOrder = SalesOrder::query()->findOrFail($creditNote->sales_order_id);
        $this->recalculatePaymentStatus($salesOrder);

        $this->renderAndAttachPdf($creditNote, $companySettings, $request);

        AuditLog::record(
            'invoice.credit_note_issued',
            $creditNote,
            $request->user(),
            null,
            ['credits_invoice_id' => $invoice->id, 'total_amount' => $creditNote->total_amount, 'invoice_number' => $creditNote->invoice_number],
        );

        return response()->json([
            'message' => 'Avoir emis.',
            'data' => new InvoiceResource($creditNote->fresh(['items', 'client', 'currency', 'attachments'])),
        ], Response::HTTP_CREATED);
    }

    private function buildAvoirLegalMentions(Invoice $creditedInvoice, \BackedEnum|string|null $language = null): string
    {
        return trans('documents.legal.credit_note', ['number' => $creditedInvoice->invoice_number], $this->documentLocale($language));
    }

    private function renderAndAttachPdf(Invoice $creditNote, CompanySettings $companySettings, Request $request): void
    {
        $creditNote->load(['items', 'client', 'currency', 'salesOrder', 'credits']);

        $logoBase64 = null;
        if ($companySettings->logo_path && Storage::disk('public')->exists($companySettings->logo_path)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($companySettings->logo_path));
        }

        $pdf = $this->withDocumentLocale($creditNote->language, fn () => Pdf::loadView('pdf.invoice_avoir', [
            'invoice' => $creditNote,
            'company' => $companySettings,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4'));

        $fileName = 'avoir-'.str_replace(['/', ' '], '-', $creditNote->invoice_number).'.pdf';
        $path = 'attachments/invoice/'.$fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $attachment = $creditNote->attachments()->create([
            'attachable_type' => Invoice::class,
            'attachable_id' => $creditNote->id,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_kb' => (int) round(Storage::disk('public')->size($path) / 1024),
            'is_primary' => true,
            'sort_order' => 0,
            'uploaded_by_user_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        $attachment->mediaTypes()->create(['type' => AttachmentType::INVOICE_DOCUMENT->value]);
    }
}
