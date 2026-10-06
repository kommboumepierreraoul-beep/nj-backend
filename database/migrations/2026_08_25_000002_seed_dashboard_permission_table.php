<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permission de lecture du tableau de bord (cahier des charges section 2.3), meme
    // pattern que les autres modules (ex. 2026_08_17_000009_seed_shipping_rate_permissions_
    // table) : attribuee par defaut au role ADMIN, SUPER_ADMIN contourne toujours la
    // verification (voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'dashboard.view', 'label' => 'Consulter le tableau de bord', 'description' => 'Voir les KPI temps reel du tableau de bord (CA/commission, factures en attente, taux de transformation, performance par provenance).'],
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
