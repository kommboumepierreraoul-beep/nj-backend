<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RfqItemController extends Controller
{
    public function index(Rfq $rfq): JsonResponse
    {
        return response()->json(['data' => $rfq->items()->with('product')->get()]);
    }

    public function store(Request $request, Rfq $rfq): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'custom_description' => ['nullable', 'string', 'required_without:product_id'],
            'target_quantity' => ['required', 'integer', 'min:1'],
            'target_unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = $rfq->items()->create($validated);

        AuditLog::record(
            'rfq_item.created',
            $item,
            $request->user(),
            null,
            $item->only(['product_id', 'custom_description', 'target_quantity', 'target_unit_id', 'target_price', 'notes']),
        );

        return response()->json([
            'message' => 'Ligne de RFQ ajoutee.',
            'data' => $item,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Rfq $rfq, RfqItem $rfqItem): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'custom_description' => ['nullable', 'string'],
            'target_quantity' => ['sometimes', 'integer', 'min:1'],
            'target_unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $rfqItem->only(['product_id', 'custom_description', 'target_quantity', 'target_unit_id', 'target_price', 'notes']);

        $rfqItem->update($validated);

        AuditLog::record(
            'rfq_item.updated',
            $rfqItem,
            $request->user(),
            $old,
            $rfqItem->only(['product_id', 'custom_description', 'target_quantity', 'target_unit_id', 'target_price', 'notes']),
        );

        return response()->json([
            'message' => 'Ligne de RFQ mise a jour.',
            'data' => $rfqItem,
        ]);
    }

    public function destroy(Request $request, Rfq $rfq, RfqItem $rfqItem): JsonResponse
    {
        $snapshot = $rfqItem->only(['id', 'product_id', 'custom_description', 'target_quantity', 'target_unit_id', 'target_price', 'notes']);

        $rfqItem->delete();

        AuditLog::record('rfq_item.deleted', $rfqItem, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Ligne de RFQ supprimee.']);
    }
}
