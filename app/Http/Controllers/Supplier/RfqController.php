<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\RFQStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RfqResource;
use App\Models\AuditLog;
use App\Models\Rfq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class RfqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rfqs = Rfq::query()
            ->withCount(['items', 'rfqSuppliers'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => RfqResource::collection($rfqs),
            'meta' => [
                'current_page' => $rfqs->currentPage(),
                'last_page' => $rfqs->lastPage(),
                'total' => $rfqs->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255', 'unique:rfqs,reference'],
            'status' => ['sometimes', new Enum(RFQStatus::class)],
            'request_date' => ['required', 'date'],
            'expected_response_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['reference'] = $validated['reference'] ?? 'RFQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        $validated['requested_by_user_id'] = $request->user()->id;

        $rfq = Rfq::query()->create($validated);

        AuditLog::record(
            'rfq.created',
            $rfq,
            $request->user(),
            null,
            $rfq->only(['reference', 'status', 'request_date', 'expected_response_date', 'notes']),
        );

        return response()->json([
            'message' => 'Demande de devis (RFQ) creee.',
            'data' => new RfqResource($rfq),
        ], Response::HTTP_CREATED);
    }

    public function show(Rfq $rfq): JsonResponse
    {
        return response()->json([
            'data' => new RfqResource($rfq->load(['items.product', 'rfqSuppliers.supplier', 'rfqSuppliers.quotes'])),
        ]);
    }

    public function update(Request $request, Rfq $rfq): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', new Enum(RFQStatus::class)],
            'request_date' => ['sometimes', 'date'],
            'expected_response_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $rfq->only(['reference', 'status', 'request_date', 'expected_response_date', 'notes']);

        $rfq->update($validated);

        AuditLog::record(
            'rfq.updated',
            $rfq,
            $request->user(),
            $old,
            $rfq->only(['reference', 'status', 'request_date', 'expected_response_date', 'notes']),
        );

        return response()->json([
            'message' => 'Demande de devis (RFQ) mise a jour.',
            'data' => new RfqResource($rfq),
        ]);
    }

    public function destroy(Request $request, Rfq $rfq): JsonResponse
    {
        $snapshot = $rfq->only(['id', 'reference', 'status', 'request_date', 'expected_response_date', 'notes']);

        $rfq->delete();

        AuditLog::record('rfq.deleted', $rfq, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Demande de devis (RFQ) supprimee.']);
    }
}
