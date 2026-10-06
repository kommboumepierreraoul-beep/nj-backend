<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierCommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SupplierCommunicationLogController extends Controller
{
    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->communicationLogs()->with('attachment')->orderByDesc('occurred_at')->get()]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', new Enum(CommunicationChannel::class)],
            'direction' => ['required', new Enum(CommunicationDirection::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'attachment_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'occurred_at' => ['required', 'date'],
        ]);

        $validated['logged_by_user_id'] = $request->user()->id;

        $log = $supplier->communicationLogs()->create($validated);

        AuditLog::record(
            'supplier_communication_log.created',
            $log,
            $request->user(),
            null,
            $log->only(['channel', 'direction', 'subject', 'summary', 'attachment_id', 'occurred_at']),
        );

        return response()->json([
            'message' => 'Echange avec le fournisseur enregistre.',
            'data' => $log,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Supplier $supplier, SupplierCommunicationLog $supplierCommunicationLog): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['sometimes', new Enum(CommunicationChannel::class)],
            'direction' => ['sometimes', new Enum(CommunicationDirection::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'summary' => ['sometimes', 'string'],
            'attachment_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'occurred_at' => ['sometimes', 'date'],
        ]);

        $old = $supplierCommunicationLog->only(['channel', 'direction', 'subject', 'summary', 'attachment_id', 'occurred_at']);

        $supplierCommunicationLog->update($validated);

        AuditLog::record(
            'supplier_communication_log.updated',
            $supplierCommunicationLog,
            $request->user(),
            $old,
            $supplierCommunicationLog->only(['channel', 'direction', 'subject', 'summary', 'attachment_id', 'occurred_at']),
        );

        return response()->json([
            'message' => 'Echange avec le fournisseur mis a jour.',
            'data' => $supplierCommunicationLog,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, SupplierCommunicationLog $supplierCommunicationLog): JsonResponse
    {
        $snapshot = $supplierCommunicationLog->only(['id', 'channel', 'direction', 'subject', 'summary', 'attachment_id', 'occurred_at']);

        $supplierCommunicationLog->delete();

        AuditLog::record('supplier_communication_log.deleted', $supplierCommunicationLog, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Echange avec le fournisseur supprime.']);
    }
}
