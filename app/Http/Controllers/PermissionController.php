<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

// Module "gestion des permissions systeme" cote lecture/matrice par role
// (Doc/spec_pages_utilisateurs.md, pages D1 et D2). Les permissions
// individuelles d'un utilisateur precis restent gerees par
// UserManagementController::updatePermissions().
//
// D1 : catalogue en lecture seule. Les codes sont crees par migration/seed
// (decision 10.3) car ils sont adosses a des verifications codees dans les
// controleurs/middlewares : pas de creation depuis l'UI.
//
// D2 : matrice role x permission. Seul ADMIN est reellement pilotable ;
// SUPER_ADMIN court-circuite toujours User::hasPermission() et n'a donc pas
// besoin d'entree explicite dans permission_role (l'UI l'affiche coche et en
// lecture seule).
class PermissionController extends Controller
{
    /** D1 — `GET /api/permissions` : catalogue complet, lecture seule. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()
                ->orderBy('code')
                ->get(['code', 'label', 'description']),
        ]);
    }

    /** D2 — `GET /api/roles/{role}/permissions` : codes accordes a ce role. */
    public function showRole(string $role): JsonResponse
    {
        $role = $this->normalizeRole($role);

        // SUPER_ADMIN contourne toute verification : renvoyer l'ensemble du
        // catalogue permet a l'UI d'afficher toutes les cases cochees sans
        // supposer un contenu particulier de permission_role.
        if ($role === UserRole::SUPER_ADMIN->value) {
            return response()->json([
                'data' => Permission::query()->orderBy('code')->pluck('code'),
            ]);
        }

        return response()->json([
            'data' => $this->roleCodes($role),
        ]);
    }

    /** D2 — `PUT /api/roles/{role}/permissions` : remplace la liste des codes du role. */
    public function updateRole(Request $request, string $role): JsonResponse
    {
        $role = $this->normalizeRole($role);

        // Le SUPER_ADMIN n'est pas editable : sa reponse a hasPermission() est
        // toujours vraie, une entree dans permission_role n'aurait aucun effet.
        if ($role === UserRole::SUPER_ADMIN->value) {
            return response()->json([
                'message' => 'Les permissions du role SUPER_ADMIN ne sont pas modifiables : ce role contourne systematiquement toute verification.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,code'],
        ]);

        $old = $this->roleCodes($role);

        $permissionIds = Permission::query()
            ->whereIn('code', $validated['permissions'])
            ->pluck('id');

        DB::transaction(function () use ($role, $permissionIds): void {
            DB::table('permission_role')->where('role', $role)->delete();

            if ($permissionIds->isNotEmpty()) {
                DB::table('permission_role')->insert(
                    $permissionIds->map(fn ($id) => ['role' => $role, 'permission_id' => $id])->all()
                );
            }
        });

        $new = $this->roleCodes($role);

        AuditLog::record(
            action: 'role.permissions_updated',
            entity: $request->user(),
            actor: $request->user(),
            old: ['role' => $role, 'permissions' => $old],
            new: ['role' => $role, 'permissions' => $new],
        );

        return response()->json([
            'message' => 'Permissions du role mises a jour.',
            'data' => $new,
        ]);
    }

    /** @return array<int, string> */
    private function roleCodes(string $role): array
    {
        return DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role', $role)
            ->orderBy('permissions.code')
            ->pluck('permissions.code')
            ->all();
    }

    private function normalizeRole(string $role): string
    {
        $role = strtoupper($role);

        if (UserRole::tryFrom($role) === null) {
            abort(Response::HTTP_NOT_FOUND, "Role inconnu : {$role}.");
        }

        return $role;
    }
}
