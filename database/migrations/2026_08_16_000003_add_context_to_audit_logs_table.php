<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Complete le journal d'audit metier avec le contexte de la requete
    // (Doc/audit_trace_systeme.md, section 3.3) : permet de savoir d'ou une
    // action a ete effectuee. Colonnes nullables, ajout retrocompatible qui
    // ne casse pas les 6 appels a AuditLog::record() deja en place dans
    // UserManagementController.
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('new_value_json');
            $table->string('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
