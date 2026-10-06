<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions du module Analyse des flux (Doc/analyse_flux_modele_donnees.md, decision
    // §0bis.5), meme pattern que les autres modules : ADMIN par defaut, SUPER_ADMIN
    // contourne toujours la verification (voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'flow_analytics.view', 'label' => "Consulter l'analyse des flux", 'description' => "Voir les indicateurs de flux achat/vente/financier/activite et le rapport des goulots d'etranglement, y compris leur export CSV/PDF."],
        ['code' => 'flow_analytics.manage', 'label' => "Gerer les seuils d'analyse des flux", 'description' => "Creer, modifier et supprimer les seuils d'alerte (flow_stage_thresholds) utilises pour detecter les goulots d'etranglement."],
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
