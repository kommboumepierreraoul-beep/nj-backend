<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\SupplierReliability;
use App\Enums\SupplierVerificationMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\AuditLog;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->with('country')
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('is_blacklisted'), fn ($query) => $query->where('is_blacklisted', $request->boolean('is_blacklisted')))
            ->when($request->filled('reliability'), fn ($query) => $query->where('reliability', $request->input('reliability')))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('categories', fn ($inner) => $inner->where('product_categories.id', $request->input('category_id'))))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($inner) use ($request) {
                $search = '%'.$request->input('search').'%';
                $inner->where('company_name', 'like', $search)->orWhere('contact_name', 'like', $search);
            }))
            ->orderBy('company_name')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SupplierResource::collection($suppliers),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'total' => $suppliers->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'wechat_id' => ['nullable', 'string', 'max:100'],
            'alibaba_profile_url' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'reliability' => ['sometimes', new Enum(SupplierReliability::class)],
            'payment_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['created_by_user_id'] = $request->user()->id;

        $supplier = Supplier::query()->create($validated);

        AuditLog::record(
            'supplier.created',
            $supplier,
            $request->user(),
            null,
            $supplier->only(['company_name', 'legal_name', 'contact_name', 'phone', 'whatsapp', 'wechat_id', 'alibaba_profile_url', 'email', 'website', 'province', 'city', 'address_line', 'country_id', 'reliability', 'payment_terms', 'notes', 'is_active']),
        );

        return response()->json([
            'message' => 'Fournisseur cree.',
            'data' => new SupplierResource($supplier),
        ], Response::HTTP_CREATED);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'data' => new SupplierResource($supplier->load([
                'country', 'contacts', 'categories', 'bankAccounts', 'documents', 'evaluations', 'attachments.mediaTypes',
            ])),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'wechat_id' => ['nullable', 'string', 'max:100'],
            'alibaba_profile_url' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'reliability' => ['sometimes', new Enum(SupplierReliability::class)],
            'payment_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $supplier->only(['company_name', 'legal_name', 'contact_name', 'phone', 'whatsapp', 'wechat_id', 'alibaba_profile_url', 'email', 'website', 'province', 'city', 'address_line', 'country_id', 'reliability', 'payment_terms', 'notes', 'is_active']);

        $supplier->update($validated);

        AuditLog::record(
            'supplier.updated',
            $supplier,
            $request->user(),
            $old,
            $supplier->only(['company_name', 'legal_name', 'contact_name', 'phone', 'whatsapp', 'wechat_id', 'alibaba_profile_url', 'email', 'website', 'province', 'city', 'address_line', 'country_id', 'reliability', 'payment_terms', 'notes', 'is_active']),
        );

        return response()->json([
            'message' => 'Fournisseur mis a jour.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $snapshot = $supplier->only(['id', 'company_name', 'legal_name', 'contact_name', 'phone', 'whatsapp', 'wechat_id', 'alibaba_profile_url', 'email', 'website', 'province', 'city', 'address_line', 'country_id', 'reliability', 'payment_terms', 'notes', 'is_active']);

        $supplier->delete();

        AuditLog::record('supplier.deleted', $supplier, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Fournisseur supprime.']);
    }

    public function verify(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'verification_method' => ['required', new Enum(SupplierVerificationMethod::class)],
        ]);

        $old = $supplier->only(['is_verified', 'verified_at', 'verification_method']);

        $supplier->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_method' => $validated['verification_method'],
        ]);

        AuditLog::record(
            'supplier.verified',
            $supplier,
            $request->user(),
            $old,
            $supplier->only(['is_verified', 'verified_at', 'verification_method']),
        );

        return response()->json([
            'message' => 'Fournisseur verifie.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function blacklist(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'is_blacklisted' => ['required', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'required_if:is_blacklisted,true'],
        ]);

        $old = $supplier->only(['is_blacklisted', 'blacklist_reason']);

        $supplier->update([
            'is_blacklisted' => $validated['is_blacklisted'],
            'blacklist_reason' => $validated['is_blacklisted'] ? ($validated['blacklist_reason'] ?? null) : null,
        ]);

        AuditLog::record(
            'supplier.blacklist_updated',
            $supplier,
            $request->user(),
            $old,
            $supplier->only(['is_blacklisted', 'blacklist_reason']),
        );

        return response()->json([
            'message' => $validated['is_blacklisted'] ? 'Fournisseur mis sur liste noire.' : 'Fournisseur retire de la liste noire.',
            'data' => new SupplierResource($supplier),
        ]);
    }
}
