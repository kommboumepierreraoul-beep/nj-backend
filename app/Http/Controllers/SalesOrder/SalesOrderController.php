<?php

namespace App\Http\Controllers\SalesOrder;

use App\Enums\BillingMode;
use App\Enums\CommissionType;
use App\Enums\SalesOrderItemType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\TransportMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CommissionRule;
use App\Models\CompanySettings;
use App\Models\SalesOrder;
use App\Notifications\SalesOrderStatusChangedNotification;
use App\Support\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $salesOrders = SalesOrder::query()
            ->with(['client', 'currency'])
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->input('client_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->input('payment_status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SalesOrderResource::collection($salesOrders),
            'meta' => [
                'current_page' => $salesOrders->currentPage(),
                'last_page' => $salesOrders->lastPage(),
                'total' => $salesOrders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'type' => ['required', new Enum(SalesOrderType::class)],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'billing_mode' => ['nullable', new Enum(BillingMode::class)],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            // TVA (Doc/tva_addendum.md) : taux unique pour la commande. Omis => valeur par
            // defaut societe (company_settings.default_tax_rate) ; 0/null => aucune TVA.
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'transport_mode' => ['nullable', new Enum(TransportMode::class)],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'order_date' => ['required', 'date'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.item_type' => ['required', new Enum(SalesOrderItemType::class)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_proposed_option' => ['sometimes', 'boolean'],
            'items.*.is_selected' => ['sometimes', 'boolean'],
            'items.*.sourced_purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.estimated_weight_kg' => ['nullable', 'numeric'],
            'items.*.estimated_volume_cbm' => ['nullable', 'numeric'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        $itemsInput = $validated['items'] ?? [];
        $this->assertItemsAreConsistent($itemsInput);

        $client = Client::query()->findOrFail($validated['client_id']);

        $itemsWithSubtotal = collect($itemsInput)->map(function (array $item) {
            $item['subtotal'] = round(((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0), 2);

            return $item;
        });

        $subtotalAmount = round((float) $itemsWithSubtotal->sum('subtotal'), 2);

        // Resolution de la commission (Doc/commandes_modele_donnees.md, section 2.2,
        // "Regle d'application") : priorite absolue au taux personnalise du client,
        // sinon premier palier actif de commission_rules couvrant le sous-total.
        $commission = $this->resolveCommission($client, $subtotalAmount);

        $discountAmount = round((float) ($validated['discount_amount'] ?? 0), 2);

        $taxRateInput = array_key_exists('tax_rate', $validated) ? $validated['tax_rate'] : null;
        $taxRate = $taxRateInput !== null
            ? (float) $taxRateInput
            : (float) (CompanySettings::current()->default_tax_rate ?? 0);
        $tax = self::computeTax($subtotalAmount, $discountAmount, (float) $commission['commission_amount'], $taxRate);

        $validityDays = $validated['validity_days'] ?? $client->proforma_validity_days ?? 7;
        $orderDate = Carbon::parse($validated['order_date']);

        $salesOrder = DB::transaction(function () use (
            $validated, $client, $itemsWithSubtotal, $subtotalAmount, $discountAmount,
            $commission, $tax, $validityDays, $orderDate, $request
        ) {
            $salesOrder = SalesOrder::query()->create([
                'reference' => $this->generateReference($orderDate),
                'client_id' => $client->id,
                'type' => $validated['type'],
                'currency_id' => $validated['currency_id'],
                'billing_mode' => $validated['billing_mode'] ?? null,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'commission_rule_id' => $commission['commission_rule_id'],
                'commission_type' => $commission['commission_type'],
                'commission_rate_applied' => $commission['commission_rate_applied'],
                'commission_amount' => $commission['commission_amount'],
                'tax_rate' => $tax['tax_rate'],
                'tax_amount' => $tax['tax_amount'],
                'total_amount' => $tax['total_amount'],
                'transport_mode' => $validated['transport_mode'] ?? null,
                'carrier_name' => $validated['carrier_name'] ?? null,
                'tracking_number' => $validated['tracking_number'] ?? null,
                'order_date' => $orderDate->toDateString(),
                'validity_days' => $validityDays,
                'valid_until' => $orderDate->copy()->addDays((int) $validityDays)->toDateString(),
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($itemsWithSubtotal as $index => $item) {
                $salesOrder->items()->create([
                    'item_type' => $item['item_type'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'label' => $item['label'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'subtotal' => $item['subtotal'],
                    'is_proposed_option' => $item['is_proposed_option'] ?? false,
                    'is_selected' => $item['is_selected'] ?? true,
                    'sourced_purchase_order_item_id' => $item['sourced_purchase_order_item_id'] ?? null,
                    'estimated_weight_kg' => $item['estimated_weight_kg'] ?? null,
                    'estimated_volume_cbm' => $item['estimated_volume_cbm'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                ]);
            }

            $salesOrder->statusHistory()->create([
                'from_status' => null,
                'to_status' => SalesOrderStatus::BROUILLON->value,
                'changed_by_user_id' => $request->user()->id,
                'reason' => null,
            ]);

            return $salesOrder;
        });

        AuditLog::record(
            'sales_order.created',
            $salesOrder,
            $request->user(),
            null,
            $salesOrder->only(['reference', 'client_id', 'type', 'subtotal_amount', 'commission_amount', 'total_amount']),
        );

        return response()->json([
            'message' => 'Commande client creee.',
            'data' => new SalesOrderResource($salesOrder->load(['client', 'currency', 'commissionRule', 'items'])),
        ], Response::HTTP_CREATED);
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => new SalesOrderResource($salesOrder->load([
                'client', 'currency', 'commissionRule', 'items.productVariant', 'payments', 'statusHistory',
            ])),
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'billing_mode' => ['sometimes', 'nullable', new Enum(BillingMode::class)],
            'transport_mode' => ['sometimes', 'nullable', new Enum(TransportMode::class)],
            'estimated_weight_kg' => ['nullable', 'numeric'],
            'estimated_volume_cbm' => ['nullable', 'numeric'],
            'actual_weight_kg' => ['nullable', 'numeric'],
            'actual_volume_cbm' => ['nullable', 'numeric'],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            // TVA ajustable tant que la commande vit (Doc/tva_addendum.md) : recalcule
            // tax_amount/total_amount sur le snapshot de montants actuel. La commission
            // reste figee (Doc/commandes_modele_donnees.md, section 9).
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (array_key_exists('tax_rate', $validated)) {
            $tax = self::computeTax(
                (float) $salesOrder->subtotal_amount,
                (float) $salesOrder->discount_amount,
                (float) $salesOrder->commission_amount,
                $validated['tax_rate'] !== null ? (float) $validated['tax_rate'] : null,
            );
            $validated['tax_rate'] = $tax['tax_rate'];
            $validated['tax_amount'] = $tax['tax_amount'];
            $validated['total_amount'] = $tax['total_amount'];
        }

        $salesOrder->update($validated);

        return response()->json([
            'message' => 'Commande client mise a jour.',
            'data' => new SalesOrderResource($salesOrder->load(['client', 'currency'])),
        ]);
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        // Jamais de suppression physique (Doc/commandes_modele_donnees.md, section 2) :
        // SoftDeletes uniquement, meme regle que clients.deleted_at.
        $salesOrder->delete();

        return response()->json(['message' => 'Commande client supprimee.']);
    }

    public function updateStatus(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(SalesOrderStatus::class)],
            'reason' => ['nullable', 'string'],
        ]);

        $newStatus = SalesOrderStatus::from($validated['status']);

        if ($newStatus === SalesOrderStatus::ANNULEE && empty($validated['reason'])) {
            return response()->json([
                'message' => 'Un motif est requis pour annuler une commande.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($newStatus === SalesOrderStatus::PROFORMA_ENVOYEE && ! $salesOrder->items()->where('is_selected', true)->exists()) {
            return response()->json([
                'message' => "La commande doit comporter au moins une ligne selectionnee avant l'envoi de la proforma.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $salesOrder->status;

        $timestampField = match ($newStatus) {
            SalesOrderStatus::CONFIRMEE => 'confirmed_at',
            SalesOrderStatus::EXPEDIEE => 'shipped_at',
            SalesOrderStatus::LIVREE => 'delivered_at',
            SalesOrderStatus::CLOTUREE => 'closed_at',
            SalesOrderStatus::ANNULEE => 'cancelled_at',
            default => null,
        };

        $updates = ['status' => $newStatus->value];
        if ($timestampField !== null) {
            $updates[$timestampField] = now();
        }
        if ($newStatus === SalesOrderStatus::ANNULEE) {
            $updates['cancellation_reason'] = $validated['reason'];
        }

        $salesOrder->update($updates);

        $salesOrder->statusHistory()->create([
            'from_status' => $oldStatus?->value,
            'to_status' => $newStatus->value,
            'changed_by_user_id' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
        ]);

        AuditLog::record(
            $newStatus === SalesOrderStatus::ANNULEE ? 'sales_order.cancelled' : 'sales_order.status_changed',
            $salesOrder,
            $request->user(),
            ['status' => $oldStatus?->value],
            ['status' => $newStatus->value],
        );

        // Evenement #2 du module Notifications (Doc/notifications_modele_donnees.md, §5) :
        // best-effort, ne bloque jamais la reponse ci-dessous (voir NotificationDispatcher).
        NotificationDispatcher::notifyCreatorOrAdmins(
            $salesOrder->createdBy,
            new SalesOrderStatusChangedNotification($salesOrder, $oldStatus, $newStatus),
        );

        return response()->json([
            'message' => 'Statut de la commande mis a jour.',
            'data' => new SalesOrderResource($salesOrder),
        ]);
    }

    // TVA (Doc/tva_addendum.md) : base taxable = sous-total - remise + commission ;
    // tax_amount = base * taux / 100. Un taux nul ou null => aucune TVA (tax_rate stocke
    // a null, tax_amount = 0). Reutilise par SalesOrderItemController::recalculateAmounts()
    // pour que le total reste coherent apres chaque modification de ligne.
    public static function computeTax(float $subtotal, float $discount, float $commission, ?float $taxRate): array
    {
        $rate = $taxRate !== null ? round($taxRate, 2) : 0.0;
        $base = round($subtotal - $discount + $commission, 2);
        $amount = $rate > 0 ? round($base * $rate / 100, 2) : 0.0;

        return [
            'tax_rate' => $rate > 0 ? $rate : null,
            'tax_amount' => $amount,
            'total_amount' => round($base + $amount, 2),
        ];
    }

    // "on creation" (voir instructions du module) : la commission est resolue et figee
    // une seule fois, a la creation de la commande. Les ajouts/modifications de lignes
    // ulterieurs (SalesOrderItemController) recalculent subtotal_amount/total_amount
    // mais ne touchent plus jamais commission_rule_id/commission_type/
    // commission_rate_applied/commission_amount (Doc/commandes_modele_donnees.md,
    // section 9, "Prix figes, jamais recalcules").
    private function resolveCommission(Client $client, float $subtotalAmount): array
    {
        if ($client->has_custom_commission) {
            $rate = (float) $client->custom_commission_rate;

            return [
                'commission_rule_id' => null,
                'commission_type' => CommissionType::POURCENTAGE->value,
                'commission_rate_applied' => $rate,
                'commission_amount' => round($subtotalAmount * $rate / 100, 2),
            ];
        }

        $rule = CommissionRule::resolveFor($subtotalAmount);

        if (! $rule) {
            throw ValidationException::withMessages([
                'client_id' => "Aucun barème de commission actif ne couvre ce montant. Configurez un palier dans Paramètres → Commissions.",
            ]);
        }

        $isPercentage = $rule->commission_type === CommissionType::POURCENTAGE;

        return [
            'commission_rule_id' => $rule->id,
            'commission_type' => $rule->commission_type->value,
            'commission_rate_applied' => $isPercentage ? (float) $rule->rate_or_amount : null,
            'commission_amount' => $isPercentage
                ? round($subtotalAmount * (float) $rule->rate_or_amount / 100, 2)
                : round((float) $rule->rate_or_amount, 2),
        ];
    }

    // Une commande PRODUIT (item_type PRODUIT) doit porter un product_variant_id, une
    // ligne SERVICE (item_type SERVICE, prestation de sourcing seule) doit porter un
    // libelle (Doc/commandes_modele_donnees.md, section 3).
    private function assertItemsAreConsistent(array $items): void
    {
        foreach ($items as $index => $item) {
            if (($item['item_type'] ?? null) === SalesOrderItemType::PRODUIT->value && empty($item['product_variant_id'])) {
                throw ValidationException::withMessages([
                    "items.$index.product_variant_id" => 'Le variant produit est requis pour une ligne de type PRODUIT.',
                ]);
            }

            if (($item['item_type'] ?? null) === SalesOrderItemType::SERVICE->value && empty($item['label'])) {
                throw ValidationException::withMessages([
                    "items.$index.label" => 'Le libelle est requis pour une ligne de type SERVICE.',
                ]);
            }
        }
    }

    // Format NJG-AAAA-MMJJ-### impose par le cahier des charges (§3.3), incrementale
    // par jour. Verrou applicatif (transaction + lockForUpdate) pour eviter une
    // collision en cas de creation concurrente (Doc/commandes_modele_donnees.md,
    // section 9).
    private function generateReference(Carbon $orderDate): string
    {
        return DB::transaction(function () use ($orderDate) {
            $prefix = 'NJG-'.$orderDate->format('Y').'-'.$orderDate->format('md').'-';

            $lastReference = SalesOrder::query()
                ->where('reference', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $nextSequence = $lastReference ? ((int) substr($lastReference, -3)) + 1 : 1;

            return $prefix.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
        });
    }
}
