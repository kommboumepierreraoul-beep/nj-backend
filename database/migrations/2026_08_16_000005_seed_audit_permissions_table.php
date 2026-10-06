<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions de lecture pour le systeme d'audit et de trace
    // (Doc/audit_trace_systeme.md), attribuees par defaut au role ADMIN (le
    // role SUPER_ADMIN contourne toujours la verification de permission,
    // voir User::hasPermission()). Journaux en lecture seule : aucune
    // permission "manage" n'a de sens ici, aucune route d'ecriture n'est
    // exposee sur ces deux journaux.
    private const PERMISSIONS = [
        ['code' => 'audit_logs.view', 'label' => "Consulter le journal d'audit", 'description' => "Voir l'historique des actions sensibles effectuees sur les entites metier (utilisateurs, produits, fournisseurs, clients...)."],
        ['code' => 'system_traces.view', 'label' => 'Consulter la trace systeme', 'description' => "Voir l'historique des evenements techniques et de securite (connexions, deconnexions, acces refuses)."],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => $permission['code'],
                'label' => $permission['label'],
                'description' => $permission['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permission_role')->insert([
                'role' => 'ADMIN',
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $codes = array_column(self::PERMISSIONS, 'code');

        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
