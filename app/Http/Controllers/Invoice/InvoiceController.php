<?php

namespace App\Http\Controllers\Invoice;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Support\WhatsAppDocumentSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lecture generique des documents facture (PROFORMA/FACTURE/AVOIR confondus), en
 * complement de ProformaController::index() qui ne liste que les PROFORMA. Necessaire
 * depuis que la FACTURE (emission automatique, SalesOrderPaymentController) et l'AVOIR
 * (CreditNoteController) sont produits sans endpoint de creation dedie cote client — sans
 * ceci, une FACTURE auto-emise ne serait retrouvable qu'en base (Doc/factures_modele_donnees.md,
 * section 9).
 *
 * sendWhatsapp()/relanceWhatsapp() (Doc/communication_whatsapp_manuelle.md) : envoi manuel
 * (jamais automatise, decision §2.4 du cahier des charges toujours en vigueur) d'un document
 * PROFORMA/FACTURE ou d'une relance au client par WhatsApp, declenche par un clic explicite
 * d'un utilisateur — a la difference du module Notifications (interne, best-effort), un echec
 * ici doit remonter clairement a l'utilisateur qui a demande l'envoi.
 */
class InvoiceController extends Controller
{
    /**
     * Registre transverse des documents (Doc/design_system_maquette_complete.md § 3, page
     * « Factures » autonome — demande utilisateur du 2026-09-03) : liste paginee de tous les
     * PROFORMA/FACTURE/AVOIR, toutes commandes confondues, filtrable par type, statut,
     * client, commande, periode d'emission et recherche libre (numero / nom client).
     * Complete index() (par commande) sans le remplacer. Lecture seule, permission
     * "invoices.view".
     */
    public function registry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['sometimes', new Enum(InvoiceDocumentType::class)],
            'status' => ['sometimes', new Enum(InvoiceStatus::class)],
            'client_id' => ['sometimes', 'integer'],
            'sales_order_id' => ['sometimes', 'integer'],
            // Pas de contrainte croisee 'after_or_equal:from' : une seule des deux bornes
            // peut etre fournie (le filtre frontend a deux champs date independants). Un
            // intervalle inverse renvoie simplement zero ligne, pas une erreur 422.
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $invoices = Invoice::query()
            ->with(['currency', 'issuedBy', 'attachments', 'credits', 'client'])
            ->when(isset($validated['document_type']), fn ($query) => $query->where('document_type', $validated['document_type']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['client_id']), fn ($query) => $query->where('client_id', $validated['client_id']))
            ->when(isset($validated['sales_order_id']), fn ($query) => $query->where('sales_order_id', $validated['sales_order_id']))
            ->when(isset($validated['from']), fn ($query) => $query->whereDate('issued_at', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($query) => $query->whereDate('issued_at', '<=', $validated['to']))
            ->when(isset($validated['search']), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('invoice_number', 'like', '%'.$validated['search'].'%')
                ->orWhere('client_name', 'like', '%'.$validated['search'].'%')))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function index(SalesOrder $salesOrder): JsonResponse
    {
        // Correctif : 'currency'/'issuedBy'/'credits' n'etaient pas eager-charges alors que
        // InvoiceResource les expose desormais (voir son commentaire) — 'currency' en
        // particulier etait deja lu par le frontend (row.currency.code, InvoiceHistoryTab)
        // sans jamais etre renvoye par cette route, provoquant une exception a l'affichage
        // de l'onglet Documents.
        $invoices = $salesOrder->invoices()
            ->with(['items', 'attachments', 'currency', 'issuedBy', 'credits'])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => InvoiceResource::collection($invoices)]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => new InvoiceResource($invoice->load(['items', 'client', 'currency', 'attachments', 'credits', 'supersedes', 'issuedBy'])),
        ]);
    }

    // Envoi initial d'un document au client (Doc/communication_whatsapp_manuelle.md, §2,
    // evenements "proforma_send"/"facture_send" selon document_type). Refuse un document
    // REMPLACEE/ANNULEE (une version obsolete ne doit jamais partir au client) — pas de
    // restriction sur EMISE/ENVOYEE : un renvoi manuel reste possible (ex. client qui a perdu
    // le message), chaque appel reste trace par AuditLog.
    public function sendWhatsapp(Request $request, Invoice $invoice): JsonResponse
    {
        if (in_array($invoice->document_type, [InvoiceDocumentType::AVOIR, InvoiceDocumentType::RECU], true)) {
            return response()->json([
                'message' => "L'envoi WhatsApp n'est pas disponible pour ce type de document (AVOIR / RECU hors perimetre, voir Doc/communication_whatsapp_manuelle.md §7 et Doc/factures_recu_addendum.md).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (in_array($invoice->status, [InvoiceStatus::ANNULEE, InvoiceStatus::REMPLACEE], true)) {
            return response()->json([
                'message' => 'Ce document est annule ou remplace par une version plus recente : impossible de l\'envoyer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $eventKey = $invoice->document_type === InvoiceDocumentType::FACTURE ? 'facture_send' : 'proforma_send';

        $result = $this->attemptSend($invoice, $eventKey, $this->buildTemplateParams($invoice));

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $invoice->forceFill([
            'sent_at' => now(),
            'status' => InvoiceStatus::ENVOYEE,
        ])->save();

        AuditLog::record(
            'invoice.sent_whatsapp',
            $invoice,
            $request->user(),
            null,
            ['document_type' => $invoice->document_type->value, 'sent_at' => $invoice->sent_at],
        );

        return response()->json([
            'message' => 'Document envoye au client par WhatsApp.',
            'data' => new InvoiceResource($invoice->fresh(['items', 'client', 'currency', 'attachments'])),
        ]);
    }

    // Relance manuelle (Doc/communication_whatsapp_manuelle.md, §2, evenement
    // "proforma_relance") : reservee aux PROFORMA deja envoyees, jamais aux FACTURE (une
    // facture deja emise/payee ne se "relance" pas de la meme facon, hors perimetre ici) ni
    // a une version obsolete. Aucun seuil de jours impose cote serveur — c'est une decision
    // manuelle de l'utilisateur, le seuil RELANCE_PROCHE_SEUIL_JOURS reste seulement un
    // indicateur d'affichage cote frontend (meme reutilisation que le module Notifications).
    public function relanceWhatsapp(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->document_type !== InvoiceDocumentType::PROFORMA) {
            return response()->json([
                'message' => 'La relance WhatsApp n\'est disponible que pour une PROFORMA.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (in_array($invoice->status, [InvoiceStatus::ANNULEE, InvoiceStatus::REMPLACEE], true)) {
            return response()->json([
                'message' => 'Ce document est annule ou remplace par une version plus recente : impossible de le relancer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->attemptSend($invoice, 'proforma_relance', $this->buildTemplateParams($invoice));

        if ($result instanceof JsonResponse) {
            return $result;
        }

        AuditLog::record(
            'invoice.relance_whatsapp_sent',
            $invoice,
            $request->user(),
            null,
            ['sent_at' => now()->toIso8601String()],
        );

        return response()->json(['message' => 'Relance envoyee au client par WhatsApp.']);
    }

    /**
     * @param  array<string, string>  $templateParams
     */
    private function attemptSend(Invoice $invoice, string $eventKey, array $templateParams): ?JsonResponse
    {
        $client = $invoice->client()->firstOrFail();

        $templateId = WhatsAppDocumentSender::templateIdFor($eventKey);
        if (! $templateId) {
            return response()->json([
                'message' => "Aucun template WhatsApp Meta configure pour cet evenement ({$eventKey}) — voir Doc/communication_whatsapp_manuelle.md §4.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $toPhone = WhatsAppDocumentSender::clientWhatsAppNumber($client);
        if (! $toPhone) {
            return response()->json([
                'message' => 'Aucun contact WhatsApp renseigne pour ce client.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $senderNumber = WhatsAppDocumentSender::companyWhatsAppNumber();
        if (! $senderNumber) {
            return response()->json([
                'message' => "Aucun numero WhatsApp configure pour l'entreprise (company_settings.whatsapp).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            WhatsAppDocumentSender::send($toPhone, $senderNumber, $templateId, $templateParams);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => "L'envoi WhatsApp a echoue : ".$exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return null;
    }

    /**
     * Positions "1"/"2"/... provisoires (Doc/communication_whatsapp_manuelle.md, §4) : a
     * reordonner/completer une fois le template reellement approuve par Meta connu — la
     * structure du template pilote l'ordre, pas l'inverse.
     *
     * @return array<string, string>
     */
    private function buildTemplateParams(Invoice $invoice): array
    {
        $pdfAttachment = $invoice->attachments()->where('is_primary', true)->first()
            ?? $invoice->attachments()->first();

        return [
            '1' => $invoice->client_name,
            '2' => $invoice->invoice_number,
            '3' => number_format((float) $invoice->total_amount, 2).' '.($invoice->currency?->code ?? ''),
            '4' => $invoice->due_date?->format('d/m/Y') ?? '',
            '5' => $pdfAttachment ? Storage::disk('public')->url($pdfAttachment->file_path) : '',
        ];
    }
}
