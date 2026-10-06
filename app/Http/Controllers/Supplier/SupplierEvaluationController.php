<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierEvaluationController extends Controller
{
    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->evaluations()->with(['purchaseOrder', 'evaluator'])->orderByDesc('evaluated_at')->get()]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'communication_score' => ['required', 'integer', 'between:1,5'],
            'delay_respect_score' => ['required', 'integer', 'between:1,5'],
            'price_competitiveness_score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
            'evaluated_at' => ['required', 'date'],
        ]);

        $validated['evaluated_by_user_id'] = $request->user()->id;

        $evaluation = $supplier->evaluations()->create($validated);

        $this->recalculateReliabilityScore($supplier);

        AuditLog::record(
            'supplier_evaluation.created',
            $evaluation,
            $request->user(),
            null,
            $evaluation->only(['purchase_order_id', 'quality_score', 'communication_score', 'delay_respect_score', 'price_competitiveness_score', 'comment', 'evaluated_at']),
        );

        return response()->json([
            'message' => 'Evaluation fournisseur enregistree.',
            'data' => $evaluation,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Supplier $supplier, SupplierEvaluation $supplierEvaluation): JsonResponse
    {
        $validated = $request->validate([
            'quality_score' => ['sometimes', 'integer', 'between:1,5'],
            'communication_score' => ['sometimes', 'integer', 'between:1,5'],
            'delay_respect_score' => ['sometimes', 'integer', 'between:1,5'],
            'price_competitiveness_score' => ['sometimes', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
            'evaluated_at' => ['sometimes', 'date'],
        ]);

        $old = $supplierEvaluation->only(['purchase_order_id', 'quality_score', 'communication_score', 'delay_respect_score', 'price_competitiveness_score', 'comment', 'evaluated_at']);

        $supplierEvaluation->update($validated);

        $this->recalculateReliabilityScore($supplier);

        AuditLog::record(
            'supplier_evaluation.updated',
            $supplierEvaluation,
            $request->user(),
            $old,
            $supplierEvaluation->only(['purchase_order_id', 'quality_score', 'communication_score', 'delay_respect_score', 'price_competitiveness_score', 'comment', 'evaluated_at']),
        );

        return response()->json([
            'message' => 'Evaluation fournisseur mise a jour.',
            'data' => $supplierEvaluation,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, SupplierEvaluation $supplierEvaluation): JsonResponse
    {
        $snapshot = $supplierEvaluation->only(['id', 'purchase_order_id', 'quality_score', 'communication_score', 'delay_respect_score', 'price_competitiveness_score', 'comment', 'evaluated_at']);

        $supplierEvaluation->delete();

        $this->recalculateReliabilityScore($supplier);

        AuditLog::record('supplier_evaluation.deleted', $supplierEvaluation, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Evaluation fournisseur supprimee.']);
    }

    // La note globale du fournisseur reflete la moyenne de ses evaluations. Sans
    // aucune evaluation, on remet reliability_score a null plutot qu'a 0 : "pas
    // encore evalue" n'est pas la meme chose qu'une note nulle.
    // reliability_score est volontairement absent du #[Fillable] de Supplier
    // (un client de l'API ne doit pas pouvoir l'ecrire directement) : on utilise
    // donc forceFill() ici, cote serveur, plutot que update().
    private function recalculateReliabilityScore(Supplier $supplier): void
    {
        $average = $supplier->evaluations()->avg('overall_score');

        $supplier->forceFill([
            'reliability_score' => $average !== null ? round((float) $average, 2) : null,
        ])->save();
    }
}
