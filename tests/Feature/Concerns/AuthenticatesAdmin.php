<?php

namespace Tests\Feature\Concerns;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Petites aides partagees par les tests des modules produits/fournisseurs :
 * creer un administrateur authentifie (la migration de seed attribue deja au
 * role ADMIN toutes les permissions produits, fournisseurs, RFQ et commandes
 * fournisseurs), et retirer une permission precise le temps d'un test pour
 * verifier que le middleware "permission" bloque bien l'acces.
 */
trait AuthenticatesAdmin
{
    protected function actingAsAdmin(array $attributes = []): array
    {
        $admin = User::factory()->create(array_merge(['role' => UserRole::ADMIN->value], $attributes));
        $token = $admin->createAccessToken()['access_token'];

        return [$admin, ['Authorization' => 'Bearer '.$token]];
    }

    protected function revokePermissionFromAdmin(string $code): void
    {
        $permissionId = Permission::query()->where('code', $code)->value('id');

        DB::table('permission_role')
            ->where('role', UserRole::ADMIN->value)
            ->where('permission_id', $permissionId)
            ->delete();
    }
}
