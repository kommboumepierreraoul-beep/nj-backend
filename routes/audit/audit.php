<?php

// Routes de consultation du systeme d'audit et de trace (Doc/audit_trace_systeme.md) :
// journal d'audit metier (audit_logs, "qui a fait quoi") et trace systeme
// technique (system_traces, "connexions/echecs/acces refuses"). Lecture
// seule : aucune route d'ecriture n'est exposee, les deux journaux sont
// alimentes uniquement en interne via AuditLog::record()/SystemTrace::record().

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SystemTraceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:audit_logs.view')->group(function (): void {
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        // Doit rester avant 'audit-logs/{auditLog}' : sinon "export" est capture comme un
        // identifiant d'entree et resolu en 404 par le route model binding.
        Route::get('audit-logs/export', [AuditLogController::class, 'export']);
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    });

    Route::middleware('permission:system_traces.view')->group(function (): void {
        Route::get('system-traces', [SystemTraceController::class, 'index']);
    });
});
