<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour la grille tarifaire "transport" (Doc/proforma_
    // comparatif_addendum.md, decision n°6), attribuees par defaut au role ADMIN — meme
    // pattern que 2026_08_16_000013 (commission_rules.view/manage).
    private const PERMISSIONS = [
        ['code' => 'shipping_rates.view', 'label' => 'Consulter les tarifs de transport', 'description' => 'Voir les paliers de tarifs de transport (Parametres -> Tarifs de transport).'],
        ['code' => 'shipping_rates.manage', 'label' => 'Gerer les tarifs de transport', 'description' => 'Creer, modifier et desactiver les paliers de tarifs de transport.'],
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
