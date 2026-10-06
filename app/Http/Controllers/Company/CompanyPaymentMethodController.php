<?php

namespace App\Http\Controllers\Company;

use App\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPaymentMethodResource;
use App\Models\AuditLog;
use App\Models\CompanyPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ecran "Parametres -> Entreprise -> Moyens de paiement"
 * (Doc/proforma_generation_addendum.md, section 2.2 ; Doc/design_system_maquette_complete.md
 * §5.10). Moyens de paiement affiches sur les documents PDF. Meme pattern CRUD que
 * ShippingRateController / CommissionRuleController : lecture derriere "company_settings.view",
 * ecriture derriere "company_settings.manage", chaque changement journalise dans audit_logs
 * (ces coordonnees figurent sur des documents envoyes aux clients).
 *
 * show_on_documents permet de retirer une methode des documents sans la supprimer.
 */
class CompanyPaymentMethodController extends Controller
{
    private const AUDITED_FIELDS = [
        'label', 'method_type', 'account_number', 'account_holder', 'iban', 'swift',
        'instructions', 'is_active', 'show_on_documents', 'sort_order',
    ];

    public function index(Request $request): JsonResponse
    {
        $methods = CompanyPaymentMethod::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('show_on_documents'), fn ($query) => $query->where('show_on_documents', $request->boolean('show_on_documents')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => CompanyPaymentMethodResource::collection($methods)]);
    }

    public function show(CompanyPaymentMethod $companyPaymentMethod): JsonResponse
    {
        return response()->json(['data' => new CompanyPaymentMethodResource($companyPaymentMethod)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'method_type' => ['required', new Enum(PaymentMethodType::class)],
            'account_number' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_documents' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $method = CompanyPaymentMethod::query()->create($validated);

        AuditLog::record(
            'company_payment_method.created',
            $method,
            $request->user(),
            null,
            $method->only(self::AUDITED_FIELDS),
        );

        return response()->json([
            'message' => 'Moyen de paiement cree.',
            'data' => new CompanyPaymentMethodResource($method),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, CompanyPaymentMethod $companyPaymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'method_type' => ['sometimes', new Enum(PaymentMethodType::class)],
            'account_number' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_documents' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $companyPaymentMethod->only(self::AUDITED_FIELDS);

        $companyPaymentMethod->update($validated);

        AuditLog::record(
            'company_payment_method.updated',
            $companyPaymentMethod,
            $request->user(),
            $old,
            $companyPaymentMethod->only(self::AUDITED_FIELDS),
        );

        return response()->json([
            'message' => 'Moyen de paiement mis a jour.',
            'data' => new CompanyPaymentMethodResource($companyPaymentMethod),
        ]);
    }

    public function destroy(Request $request, CompanyPaymentMethod $companyPaymentMethod): JsonResponse
    {
        // Suppression physique disponible pour la completude du CRUD ; la voie recommandee
        // pour "retirer" une methode reste is_active=false / show_on_documents=false, aucun
        // document deja emis ne referencant cette table par cle etrangere.
        $snapshot = $companyPaymentMethod->only(array_merge(['id'], self::AUDITED_FIELDS));

        $companyPaymentMethod->delete();

        AuditLog::record('company_payment_method.deleted', $companyPaymentMethod, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Moyen de paiement supprime.']);
    }
}
