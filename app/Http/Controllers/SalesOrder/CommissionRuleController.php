<?php

namespace App\Http\Controllers\SalesOrder;

use App\Enums\CommissionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionRuleResource;
use App\Models\AuditLog;
use App\Models\CommissionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

// Ecran "Parametres -> Commissions" (Doc/commandes_modele_donnees.md, section 2.2) :
// barème de commission par defaut, editable sans deploiement. Chaque changement est
// journalise dans audit_logs (section 8 du document) car il pilote directement la
// commission affichee sur toutes les commandes futures.
class CommissionRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = CommissionRule::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => CommissionRuleResource::collection($rules)]);
    }

    public function show(CommissionRule $commissionRule): JsonResponse
    {
        return response()->json(['data' => new CommissionRuleResource($commissionRule)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'commission_type' => ['required', new Enum(CommissionType::class)],
            'rate_or_amount' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $commissionRule = CommissionRule::query()->create($validated);

        AuditLog::record(
            'commission_rule.created',
            $commissionRule,
            $request->user(),
            null,
            $commissionRule->only(['label', 'min_amount', 'max_amount', 'commission_type', 'rate_or_amount']),
        );

        return response()->json([
            'message' => 'Palier de commission cree.',
            'data' => new CommissionRuleResource($commissionRule),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, CommissionRule $commissionRule): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric'],
            'commission_type' => ['sometimes', new Enum(CommissionType::class)],
            'rate_or_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $commissionRule->only(['label', 'min_amount', 'max_amount', 'commission_type', 'rate_or_amount', 'is_active']);

        $commissionRule->update($validated);

        AuditLog::record(
            'commission_rule.updated',
            $commissionRule,
            $request->user(),
            $old,
            $commissionRule->only(['label', 'min_amount', 'max_amount', 'commission_type', 'rate_or_amount', 'is_active']),
        );

        return response()->json([
            'message' => 'Palier de commission mis a jour.',
            'data' => new CommissionRuleResource($commissionRule),
        ]);
    }

    public function destroy(CommissionRule $commissionRule): JsonResponse
    {
        // Suppression physique disponible pour completude du CRUD, mais la voie
        // recommandee pour "retirer" un palier reste is_active=false (voir doc,
        // section 2.2) : les commandes deja emises ne referencent que
        // commission_rule_id (nullOnDelete), leur commission_amount reste inchangee.
        $commissionRule->delete();

        return response()->json(['message' => 'Palier de commission supprime.']);
    }
}
