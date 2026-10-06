<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderStatusChangedNotification;
use App\Support\NotificationDispatcher;
use App\Support\WhatsAppDocumentSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'currency'])
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->input('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => PurchaseOrderResource::collection($purchaseOrders),
            'meta' => [
                'current_page' => $purchaseOrders->currentPage(),
                'last_page' => $purchaseOrders->lastPage(),
                'total' => $purchaseOrders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255', 'unique:purchase_orders,reference'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'status' => ['sometimes', new Enum(PurchaseOrderStatus::class)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'notes' => ['nullable', 'string'],
            // Liaison optionnelle a la RFQ / au devis retenu dont ce PO est issu
            // (Doc/analyse_flux_modele_donnees.md, §8.4). Renseigne par le frontend quand la
            // commande est creee depuis un devis selectionne ; laisse null sinon.
            'rfq_id' => ['nullable', 'integer', 'exists:rfqs,id'],
            'rfq_supplier_quote_id' => ['nullable', 'integer', 'exists:rfq_supplier_quotes,id'],
        ]);

        $validated['reference'] = $validated['reference'] ?? 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        $validated['total_amount'] = 0;
        $validated['created_by_user_id'] = $request->user()->id;

        // Historique du flux achat (Doc/analyse_flux_modele_donnees.md, decision §0bis.1,
        // ajoute le 2026-08-26) : premiere ligne posee des la creation, meme pattern que
        // SalesOrderController::store() -> statusHistory()->create() avec from_status=null.
        $purchaseOrder = DB::transaction(function () use ($validated, $request) {
            $purchaseOrder = PurchaseOrder::query()->create($validated);

            $purchaseOrder->statusHistory()->create([
                'from_status' => null,
                'to_status' => $purchaseOrder->status->value,
                'changed_by_user_id' => $request->user()->id,
                'reason' => null,
            ]);

            return $purchaseOrder;
        });

        AuditLog::record(
            'purchase_order.created',
            $purchaseOrder,
            $request->user(),
            null,
            $purchaseOrder->only(['reference', 'supplier_id', 'status', 'order_date', 'expected_delivery_date', 'currency_id', 'notes', 'rfq_id', 'rfq_supplier_quote_id']),
        );

        return response()->json([
            'message' => 'Commande fournisseur creee.',
            'data' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'currency'])),
        ], Response::HTTP_CREATED);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'data' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'currency', 'items.productVariant'])),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(PurchaseOrderStatus::class)],
            'order_date' => ['sometimes', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'actual_delivery_date' => ['nullable', 'date'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'notes' => ['nullable', 'string'],
            'rfq_id' => ['nullable', 'integer', 'exists:rfqs,id'],
            'rfq_supplier_quote_id' => ['nullable', 'integer', 'exists:rfq_supplier_quotes,id'],
        ]);

        $old = $purchaseOrder->only(['status', 'order_date', 'expected_delivery_date', 'actual_delivery_date', 'currency_id', 'notes', 'rfq_id', 'rfq_supplier_quote_id']);

        $purchaseOrder->update($validated);

        // Historique du flux achat (Doc/analyse_flux_modele_donnees.md, decision §0bis.1) :
        // une ligne par changement de statut effectif. Ce controleur n'a pas de route
        // updateStatus() dediee (contrairement a SalesOrderController) -- le statut transite
        // via cet update() generique, on detecte donc le changement en comparant l'ancienne
        // valeur (capturee dans $old avant update()) a la nouvelle.
        if (array_key_exists('status', $validated) && $old['status']?->value !== $purchaseOrder->status->value) {
            $purchaseOrder->statusHistory()->create([
                'from_status' => $old['status']?->value,
                'to_status' => $purchaseOrder->status->value,
                'changed_by_user_id' => $request->user()->id,
                'reason' => null,
            ]);

            // Evenement #3 du module Notifications (Doc/notifications_modele_donnees.md, §5) :
            // meme detection de changement effectif que l'historique ci-dessus, pas de logique
            // dupliquee. Best-effort (voir NotificationDispatcher).
            NotificationDispatcher::notifyCreatorOrAdmins(
                $purchaseOrder->created_by_user_id ? User::query()->find($purchaseOrder->created_by_user_id) : null,
                new PurchaseOrderStatusChangedNotification($purchaseOrder, $old['status'], $purchaseOrder->status),
            );
        }

        AuditLog::record(
            'purchase_order.updated',
            $purchaseOrder,
            $request->user(),
            $old,
            $purchaseOrder->only(['status', 'order_date', 'expected_delivery_date', 'actual_delivery_date', 'currency_id', 'notes', 'rfq_id', 'rfq_supplier_quote_id']),
        );

        return response()->json([
            'message' => 'Commande fournisseur mise a jour.',
            'data' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'currency'])),
        ]);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $snapshot = $purchaseOrder->only(['id', 'reference', 'supplier_id', 'status', 'order_date', 'expected_delivery_date', 'actual_delivery_date', 'currency_id', 'notes']);

        $purchaseOrder->delete();

        AuditLog::record('purchase_order.deleted', $purchaseOrder, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Commande fournisseur supprimee.']);
    }

    // Envoi manuel de la commande fournisseur par WhatsApp (Doc/communication_whatsapp_manuelle.md,
    // §2, evenement "purchase_order_send") — jamais automatise (§0/§2.4). Meme principe que
    // RfqSupplierController::sendWhatsapp() : trace via SupplierCommunicationLog (deja
    // existant), pas de nouvelle colonne sent_at sur purchase_orders.
    public function sendWhatsapp(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $supplier = $purchaseOrder->supplier()->firstOrFail();

        $templateId = WhatsAppDocumentSender::templateIdFor('purchase_order_send');
        if (! $templateId) {
            return response()->json([
                'message' => 'Aucun template WhatsApp Meta configure pour cet evenement (purchase_order_send) — voir Doc/communication_whatsapp_manuelle.md §4.',
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
            '2' => $purchaseOrder->reference,
            '3' => number_format((float) $purchaseOrder->total_amount, 2),
            '4' => $purchaseOrder->expected_delivery_date?->format('d/m/Y') ?? '',
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
            'subject' => 'Commande fournisseur '.$purchaseOrder->reference,
            'summary' => 'Commande fournisseur envoyee par WhatsApp ('.$purchaseOrder->reference.').',
            'logged_by_user_id' => $request->user()->id,
            'occurred_at' => now(),
        ]);

        AuditLog::record(
            'purchase_order.sent_whatsapp',
            $purchaseOrder,
            $request->user(),
            null,
            ['supplier_id' => $supplier->id, 'communication_log_id' => $log->id],
        );

        return response()->json(['message' => 'Commande fournisseur envoyee par WhatsApp.']);
    }
}
