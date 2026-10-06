<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierContactController extends Controller
{
    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->contacts()->get()]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'wechat_id' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = $supplier->contacts()->create($validated);

        if ($request->boolean('is_primary')) {
            $supplier->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
        }

        AuditLog::record(
            'supplier_contact.created',
            $contact,
            $request->user(),
            null,
            $contact->only(['full_name', 'role_title', 'phone', 'wechat_id', 'email', 'is_primary', 'notes']),
        );

        return response()->json([
            'message' => 'Contact fournisseur cree.',
            'data' => $contact,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Supplier $supplier, SupplierContact $supplierContact): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'wechat_id' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $supplierContact->only(['full_name', 'role_title', 'phone', 'wechat_id', 'email', 'is_primary', 'notes']);

        $supplierContact->update($validated);

        if ($request->boolean('is_primary')) {
            $supplier->contacts()->whereKeyNot($supplierContact->id)->update(['is_primary' => false]);
        }

        AuditLog::record(
            'supplier_contact.updated',
            $supplierContact,
            $request->user(),
            $old,
            $supplierContact->only(['full_name', 'role_title', 'phone', 'wechat_id', 'email', 'is_primary', 'notes']),
        );

        return response()->json([
            'message' => 'Contact fournisseur mis a jour.',
            'data' => $supplierContact,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, SupplierContact $supplierContact): JsonResponse
    {
        $snapshot = $supplierContact->only(['id', 'full_name', 'role_title', 'phone', 'wechat_id', 'email', 'is_primary', 'notes']);

        $supplierContact->delete();

        AuditLog::record('supplier_contact.deleted', $supplierContact, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Contact fournisseur supprime.']);
    }
}
