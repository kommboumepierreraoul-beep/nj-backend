<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\SupplierDocumentType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SupplierDocumentController extends Controller
{
    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->documents()->with('attachment')->get()]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', new Enum(SupplierDocumentType::class)],
            'attachment_id' => ['required', 'integer', 'exists:attachments,id'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'is_verified' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $document = $supplier->documents()->create($validated);

        AuditLog::record(
            'supplier_document.created',
            $document,
            $request->user(),
            null,
            $document->only(['type', 'attachment_id', 'issue_date', 'expiry_date', 'is_verified', 'notes']),
        );

        return response()->json([
            'message' => 'Document fournisseur ajoute.',
            'data' => $document,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Supplier $supplier, SupplierDocument $supplierDocument): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', new Enum(SupplierDocumentType::class)],
            'attachment_id' => ['sometimes', 'integer', 'exists:attachments,id'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'is_verified' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $supplierDocument->only(['type', 'attachment_id', 'issue_date', 'expiry_date', 'is_verified', 'notes']);

        $supplierDocument->update($validated);

        AuditLog::record(
            'supplier_document.updated',
            $supplierDocument,
            $request->user(),
            $old,
            $supplierDocument->only(['type', 'attachment_id', 'issue_date', 'expiry_date', 'is_verified', 'notes']),
        );

        return response()->json([
            'message' => 'Document fournisseur mis a jour.',
            'data' => $supplierDocument,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, SupplierDocument $supplierDocument): JsonResponse
    {
        $snapshot = $supplierDocument->only(['id', 'type', 'attachment_id', 'issue_date', 'expiry_date', 'is_verified', 'notes']);

        $supplierDocument->delete();

        AuditLog::record('supplier_document.deleted', $supplierDocument, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Document fournisseur supprime.']);
    }
}
