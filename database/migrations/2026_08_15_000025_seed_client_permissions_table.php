<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour le nouveau module clients, attribuees par
    // defaut au role ADMIN (le role SUPER_ADMIN contourne toujours la
    // verification de permission, voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'clients.view', 'label' => 'Consulter les clients', 'description' => 'Voir les fiches clients, leurs contacts, categories et etiquettes.'],
        ['code' => 'clients.manage', 'label' => 'Gerer les clients', 'description' => 'Creer, modifier et supprimer clients, contacts, categories et etiquettes.'],
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
