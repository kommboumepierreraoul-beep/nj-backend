<?php

namespace App\Http\Controllers\SalesOrder;

use App\Enums\SalesOrderItemType;
use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SalesOrderItemController extends Controller
{
    public function index(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json(['data' => $salesOrder->items()->with('productVariant')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', new Enum(SalesOrderItemType::class)],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id', 'required_if:item_type,PRODUIT'],
            'label' => ['nullable', 'string', 'max:255', 'required_if:item_type,SERVICE'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'is_proposed_option' => ['sometimes', 'boolean'],
            'is_selected' => ['sometimes', 'boolean'],
            'sourced_purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'estimated_weight_kg' => ['nullable', 'numeric'],
            'estimated_volume_cbm' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['subtotal'] = round(((float) $validated['quantity'] * (float) $validated['unit_price']) - (float) ($validated['discount_amount'] ?? 0), 2);

        $item = $salesOrder->items()->create($validated);

        $this->recalculateAmounts($salesOrder);

        return response()->json([
            'message' => 'Ligne de commande ajoutee.',
            'data' => $item,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, SalesOrder $salesOrder, SalesOrderItem $salesOrderItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'is_selected' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $quantity = $validated['quantity'] ?? $salesOrderItem->quantity;
        $unitPrice = $validated['unit_price'] ?? $salesOrderItem->unit_price;
        $discount = $validated['discount_amount'] ?? $salesOrderItem->discount_amount;
        $validated['subtotal'] = round(((float) $quantity * (float) $unitPrice) - (float) $discount, 2);

        $salesOrderItem->update($validated);

        $this->recalculateAmounts($salesOrder);

        return response()->json([
            'message' => 'Ligne de commande mise a jour.',
            'data' => $salesOrderItem,
        ]);
    }

    public function destroy(SalesOrder $salesOrder, SalesOrderItem $salesOrderItem): JsonResponse
    {
        $salesOrderItem->delete();

        $this->recalculateAmounts($salesOrder);

        return response()->json(['message' => 'Ligne de commande supprimee.']);
    }

    // Le sous-total et le total de la commande derivent toujours de la somme de ses
    // lignes, mais la commission (commission_rule_id/commission_type/
    // commission_rate_applied/commission_amount) reste figee sur le snapshot pris a la
    // creation de la commande (Doc/commandes_modele_donnees.md, section 9, "Prix figes,
    // jamais recalcules") : on ne la touche jamais ici.
    private function recalculateAmounts(SalesOrder $salesOrder): void
    {
        $subtotalAmount = round((float) $salesOrder->items()->sum('subtotal'), 2);

        // La TVA (Doc/tva_addendum.md) suit le sous-total : le taux fige sur la commande
        // est conserve, seul tax_amount/total_amount est recalcule via le meme helper que
        // SalesOrderController (source unique de la formule).
        $tax = SalesOrderController::computeTax(
            $subtotalAmount,
            (float) $salesOrder->discount_amount,
            (float) $salesOrder->commission_amount,
            $salesOrder->tax_rate !== null ? (float) $salesOrder->tax_rate : null,
        );

        $salesOrder->update([
            'subtotal_amount' => $subtotalAmount,
            'tax_amount' => $tax['tax_amount'],
            'total_amount' => $tax['total_amount'],
        ]);
    }
}
