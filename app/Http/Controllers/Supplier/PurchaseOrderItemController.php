<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderItemController extends Controller
{
    public function index(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => $purchaseOrder->items()->with('productVariant')->get()]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['subtotal'] = $validated['quantity'] * $validated['unit_price'];

        $item = $purchaseOrder->items()->create($validated);

        $this->recalculateTotal($purchaseOrder);

        AuditLog::record(
            'purchase_order_item.created',
            $item,
            $request->user(),
            null,
            $item->only(['product_variant_id', 'quantity', 'unit_price', 'subtotal', 'notes']),
        );

        return response()->json([
            'message' => 'Ligne de commande ajoutee.',
            'data' => $item,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $quantity = $validated['quantity'] ?? $purchaseOrderItem->quantity;
        $unitPrice = $validated['unit_price'] ?? $purchaseOrderItem->unit_price;
        $validated['subtotal'] = $quantity * $unitPrice;

        $old = $purchaseOrderItem->only(['product_variant_id', 'quantity', 'unit_price', 'subtotal', 'notes']);

        $purchaseOrderItem->update($validated);

        $this->recalculateTotal($purchaseOrder);

        AuditLog::record(
            'purchase_order_item.updated',
            $purchaseOrderItem,
            $request->user(),
            $old,
            $purchaseOrderItem->only(['product_variant_id', 'quantity', 'unit_price', 'subtotal', 'notes']),
        );

        return response()->json([
            'message' => 'Ligne de commande mise a jour.',
            'data' => $purchaseOrderItem,
        ]);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $snapshot = $purchaseOrderItem->only(['id', 'product_variant_id', 'quantity', 'unit_price', 'subtotal', 'notes']);

        $purchaseOrderItem->delete();

        $this->recalculateTotal($purchaseOrder);

        AuditLog::record('purchase_order_item.deleted', $purchaseOrderItem, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Ligne de commande supprimee.']);
    }

    // Le montant total de la commande derive toujours de la somme de ses lignes.
    private function recalculateTotal(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->update([
            'total_amount' => $purchaseOrder->items()->sum('subtotal'),
        ]);
    }
}
