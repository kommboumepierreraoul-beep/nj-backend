<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permissions granulaires pour le module de gestion des utilisateurs.
    // "users.view" et "users.manage" sont attribuees par defaut au role ADMIN.
    // "users.manage_permissions" (delegation de la gestion des permissions
    // individuelles et des roles) n'est PAS attribuee par defaut : elle doit
    // etre accordee explicitement par un SUPER_ADMIN a un ADMIN de confiance
    // (voir Doc/spec_pages_utilisateurs.md, section 10, decision #3).
    // Le role SUPER_ADMIN contourne toujours la verification de permission
    // (voir User::hasPermission()).
    private const PERMISSIONS = [
        ['code' => 'users.view', 'label' => 'Consulter les utilisateurs', 'description' => 'Voir la liste et les fiches des utilisateurs de la plateforme.', 'grant_to_admin' => true],
        ['code' => 'users.manage', 'label' => 'Gerer les utilisateurs', 'description' => 'Creer, modifier des utilisateurs, changer leur statut, les supprimer et gerer leurs sessions actives.', 'grant_to_admin' => true],
        ['code' => 'users.manage_permissions', 'label' => 'Gerer les permissions et roles', 'description' => 'Attribuer des permissions individuelles a un utilisateur et piloter les permissions par role. Delegable par un super administrateur.', 'grant_to_admin' => false],
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

            if ($permission['grant_to_admin']) {
                DB::table('permission_role')->insert([
                    'role' => 'ADMIN',
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $codes = array_column(self::PERMISSIONS, 'code');

        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
