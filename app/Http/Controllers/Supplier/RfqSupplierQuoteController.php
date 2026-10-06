<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RfqSupplier;
use App\Models\RfqSupplierQuote;
use App\Models\User;
use App\Notifications\RfqSupplierQuoteReceivedNotification;
use App\Support\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RfqSupplierQuoteController extends Controller
{
    public function index(RfqSupplier $rfqSupplier): JsonResponse
    {
        return response()->json(['data' => $rfqSupplier->quotes()->with(['rfqItem', 'currency'])->get()]);
    }

    public function store(Request $request, RfqSupplier $rfqSupplier): JsonResponse
    {
        $validated = $request->validate([
            'rfq_item_id' => ['required', 'integer', 'exists:rfq_items,id'],
            'quoted_unit_price' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'quoted_moq' => ['nullable', 'integer', 'min:1'],
            'quoted_lead_time_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'quoted_at' => ['required', 'date'],
        ]);

        $quote = $rfqSupplier->quotes()->create($validated);

        AuditLog::record(
            'rfq_supplier_quote.created',
            $quote,
            $request->user(),
            null,
            $quote->only(['rfq_item_id', 'quoted_unit_price', 'currency_id', 'quoted_moq', 'quoted_lead_time_days', 'notes', 'quoted_at']),
        );

        // Evenement #1 du module Notifications (Doc/notifications_modele_donnees.md, §5) :
        // best-effort, notifie le demandeur du RFQ d'origine (repli sur les ADMIN/SUPER_ADMIN
        // actifs si requested_by_user_id est nul).
        $rfq = $rfqSupplier->rfq;
        NotificationDispatcher::notifyCreatorOrAdmins(
            $rfq->requested_by_user_id ? User::query()->find($rfq->requested_by_user_id) : null,
            new RfqSupplierQuoteReceivedNotification($quote),
        );

        return response()->json([
            'message' => 'Devis fournisseur enregistre.',
            'data' => $quote,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, RfqSupplier $rfqSupplier, RfqSupplierQuote $rfqSupplierQuote): JsonResponse
    {
        $validated = $request->validate([
            'quoted_unit_price' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'quoted_moq' => ['nullable', 'integer', 'min:1'],
            'quoted_lead_time_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'quoted_at' => ['sometimes', 'date'],
        ]);

        $old = $rfqSupplierQuote->only(['rfq_item_id', 'quoted_unit_price', 'currency_id', 'quoted_moq', 'quoted_lead_time_days', 'notes', 'quoted_at']);

        $rfqSupplierQuote->update($validated);

        AuditLog::record(
            'rfq_supplier_quote.updated',
            $rfqSupplierQuote,
            $request->user(),
            $old,
            $rfqSupplierQuote->only(['rfq_item_id', 'quoted_unit_price', 'currency_id', 'quoted_moq', 'quoted_lead_time_days', 'notes', 'quoted_at']),
        );

        return response()->json([
            'message' => 'Devis fournisseur mis a jour.',
            'data' => $rfqSupplierQuote,
        ]);
    }

    public function select(Request $request, RfqSupplier $rfqSupplier, RfqSupplierQuote $rfqSupplierQuote): JsonResponse
    {
        $old = $rfqSupplierQuote->only(['is_selected']);

        // Un seul devis retenu par ligne de RFQ : on desactive les autres devis de la meme ligne.
        RfqSupplierQuote::query()->where('rfq_item_id', $rfqSupplierQuote->rfq_item_id)->update(['is_selected' => false]);
        $rfqSupplierQuote->update(['is_selected' => true]);

        AuditLog::record(
            'rfq_supplier_quote.selected',
            $rfqSupplierQuote,
            $request->user(),
            $old,
            $rfqSupplierQuote->only(['is_selected']),
        );

        return response()->json([
            'message' => 'Devis retenu pour cette ligne de RFQ.',
            'data' => $rfqSupplierQuote,
        ]);
    }

    public function destroy(Request $request, RfqSupplier $rfqSupplier, RfqSupplierQuote $rfqSupplierQuote): JsonResponse
    {
        $snapshot = $rfqSupplierQuote->only(['id', 'rfq_item_id', 'quoted_unit_price', 'currency_id', 'quoted_moq', 'quoted_lead_time_days', 'notes', 'quoted_at']);

        $rfqSupplierQuote->delete();

        AuditLog::record('rfq_supplier_quote.deleted', $rfqSupplierQuote, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Devis fournisseur supprime.']);
    }
}
