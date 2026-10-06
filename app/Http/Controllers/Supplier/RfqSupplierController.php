<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\RfqSupplierStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Rfq;
use App\Models\RfqSupplier;
use App\Support\WhatsAppDocumentSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class RfqSupplierController extends Controller
{
    // Rattache un ou plusieurs fournisseurs a une RFQ ("a qui la demande de devis a-t-elle ete envoyee").
    public function index(Rfq $rfq): JsonResponse
    {
        return response()->json(['data' => $rfq->rfqSuppliers()->with('supplier')->get()]);
    }

    public function store(Request $request, Rfq $rfq): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id', 'unique:rfq_suppliers,supplier_id,NULL,id,rfq_id,'.$rfq->id],
            'status' => ['sometimes', new Enum(RfqSupplierStatus::class)],
            'sent_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['sent_at'] = $validated['sent_at'] ?? now();

        $rfqSupplier = $rfq->rfqSuppliers()->create($validated);

        AuditLog::record(
            'rfq_supplier.created',
            $rfqSupplier,
            $request->user(),
            null,
            $rfqSupplier->only(['supplier_id', 'status', 'sent_at', 'response_date', 'notes']),
        );

        return response()->json([
            'message' => 'Fournisseur ajoute a la RFQ.',
            'data' => $rfqSupplier,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Rfq $rfq, RfqSupplier $rfqSupplier): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(RfqSupplierStatus::class)],
            'response_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $rfqSupplier->only(['supplier_id', 'status', 'sent_at', 'response_date', 'notes']);

        $rfqSupplier->update($validated);

        AuditLog::record(
            'rfq_supplier.updated',
            $rfqSupplier,
            $request->user(),
            $old,
            $rfqSupplier->only(['supplier_id', 'status', 'sent_at', 'response_date', 'notes']),
        );

        return response()->json([
            'message' => 'Statut du fournisseur sur la RFQ mis a jour.',
            'data' => $rfqSupplier,
        ]);
    }

    public function destroy(Request $request, Rfq $rfq, RfqSupplier $rfqSupplier): JsonResponse
    {
        $snapshot = $rfqSupplier->only(['id', 'supplier_id', 'status', 'sent_at', 'response_date', 'notes']);

        $rfqSupplier->delete();

        AuditLog::record('rfq_supplier.deleted', $rfqSupplier, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Fournisseur retire de la RFQ.']);
    }

    // Envoi manuel du RFQ a ce fournisseur precis par WhatsApp (Doc/communication_whatsapp_manuelle.md,
    // §2, evenement "rfq_send") — jamais automatise (§0/§2.4). Ne touche PAS
    // rfq_suppliers.sent_at/status : ces colonnes existent deja et signifient autre chose
    // ("rattache administrativement a la RFQ", pose automatiquement par store() ci-dessus,
    // independant du canal reellement utilise pour contacter le fournisseur) — voir
    // Doc/communication_whatsapp_manuelle.md §3.2. La trace de cet envoi passe par
    // SupplierCommunicationLog (deja existant pour ce role, pas de nouvelle table).
    public function sendWhatsapp(Request $request, Rfq $rfq, RfqSupplier $rfqSupplier): JsonResponse
    {
        if ($rfqSupplier->rfq_id !== $rfq->id) {
            return response()->json(['message' => 'Ce fournisseur n\'est pas rattache a cette RFQ.'], Response::HTTP_NOT_FOUND);
        }

        $supplier = $rfqSupplier->supplier()->firstOrFail();

        $templateId = WhatsAppDocumentSender::templateIdFor('rfq_send');
        if (! $templateId) {
            return response()->json([
                'message' => 'Aucun template WhatsApp Meta configure pour cet evenement (rfq_send) — voir Doc/communication_whatsapp_manuelle.md §4.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $toPhone = WhatsAppDocumentSender::supplierWhatsAppNumber($supplier);
        if (! $toPhone) {
            return response()->json([
                'message' => 'Aucun numero WhatsApp renseigne pour ce fournisseur.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $senderNumber = WhatsAppDocumentSender::companyWhatsAppNumber();
        if (! $senderNumber) {
            return response()->json([
                'message' => "Aucun numero WhatsApp configure pour l'entreprise (company_settings.whatsapp).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Positions "1"/"2"/... provisoires, a reordonner une fois le template Meta connu
        // (voir Doc/communication_whatsapp_manuelle.md §4).
        $templateParams = [
            '1' => $supplier->company_name,
            '2' => $rfq->reference,
            '3' => (string) $rfq->items()->count(),
            '4' => $rfq->expected_response_date?->format('d/m/Y') ?? '',
        ];

        try {
            WhatsAppDocumentSender::send($toPhone, $senderNumber, $templateId, $templateParams);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => "L'envoi WhatsApp a echoue : ".$exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $log = $supplier->communicationLogs()->create([
            'channel' => CommunicationChannel::WHATSAPP->value,
            'direction' => CommunicationDirection::OUTGOING->value,
            'subject' => 'RFQ '.$rfq->reference,
            'summary' => 'Demande de devis envoyee par WhatsApp (RFQ '.$rfq->reference.').',
            'logged_by_user_id' => $request->user()->id,
            'occurred_at' => now(),
        ]);

        AuditLog::record(
            'rfq_supplier.sent_whatsapp',
            $rfqSupplier,
            $request->user(),
            null,
            ['supplier_id' => $supplier->id, 'communication_log_id' => $log->id],
        );

        return response()->json(['message' => 'RFQ envoyee au fournisseur par WhatsApp.']);
    }
}
