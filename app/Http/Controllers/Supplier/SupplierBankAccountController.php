<?php

namespace App\Http\Controllers\Supplier;

use App\Enums\SupplierPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SupplierBankAccountController extends Controller
{
    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->bankAccounts()->with('currency')->get()]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', new Enum(SupplierPaymentMethod::class)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $account = $supplier->bankAccounts()->create($validated);

        if ($request->boolean('is_default')) {
            $supplier->bankAccounts()->whereKeyNot($account->id)->update(['is_default' => false]);
        }

        AuditLog::record(
            'supplier_bank_account.created',
            $account,
            $request->user(),
            null,
            $account->only(['method', 'account_name', 'account_number', 'bank_name', 'swift_code', 'currency_id', 'is_default', 'is_active']),
        );

        return response()->json([
            'message' => 'Compte bancaire fournisseur cree.',
            'data' => $account,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Supplier $supplier, SupplierBankAccount $supplierBankAccount): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['sometimes', new Enum(SupplierPaymentMethod::class)],
            'account_name' => ['sometimes', 'string', 'max:255'],
            'account_number' => ['sometimes', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $supplierBankAccount->only(['method', 'account_name', 'account_number', 'bank_name', 'swift_code', 'currency_id', 'is_default', 'is_active']);

        $supplierBankAccount->update($validated);

        if ($request->boolean('is_default')) {
            $supplier->bankAccounts()->whereKeyNot($supplierBankAccount->id)->update(['is_default' => false]);
        }

        AuditLog::record(
            'supplier_bank_account.updated',
            $supplierBankAccount,
            $request->user(),
            $old,
            $supplierBankAccount->only(['method', 'account_name', 'account_number', 'bank_name', 'swift_code', 'currency_id', 'is_default', 'is_active']),
        );

        return response()->json([
            'message' => 'Compte bancaire fournisseur mis a jour.',
            'data' => $supplierBankAccount,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier, SupplierBankAccount $supplierBankAccount): JsonResponse
    {
        $snapshot = $supplierBankAccount->only(['id', 'method', 'account_name', 'account_number', 'bank_name', 'swift_code', 'currency_id', 'is_default', 'is_active']);

        $supplierBankAccount->delete();

        AuditLog::record('supplier_bank_account.deleted', $supplierBankAccount, $request->user(), $snapshot, null);

        return response()->json(['message' => 'Compte bancaire fournisseur supprime.']);
    }
}
