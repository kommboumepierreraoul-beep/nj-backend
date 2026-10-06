<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Module "gestion des permissions systeme" (Doc/spec_pages_utilisateurs.md D1/D2) :
 * catalogue en lecture seule + matrice permissions par role. Complete
 * UserManagementTest qui couvre le CRUD utilisateurs et les permissions
 * individuelles.
 */
class PermissionModuleTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function delegateManagePermissions(User $admin): void
    {
        $id = Permission::query()->where('code', 'users.manage_permissions')->value('id');
        DB::table('permission_user')->insert(['user_id' => $admin->id, 'permission_id' => $id]);
    }

    private function actingAsSuperAdmin(): array
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $token = $superAdmin->createAccessToken()['access_token'];

        return [$superAdmin, ['Authorization' => 'Bearer '.$token]];
    }

    public function test_guest_cannot_read_the_permissions_catalog(): void
    {
        $this->getJson('/api/permissions')->assertUnauthorized();
    }

    public function test_admin_with_users_view_can_read_the_permissions_catalog(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/permissions', $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'label', 'description']]])
            ->assertJsonPath('data.0.code', fn ($code) => is_string($code));
    }

    public function test_catalog_is_ordered_by_code_and_contains_seeded_codes(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $codes = $this->getJson('/api/permissions', $headers)->json('data.*.code');

        $this->assertContains('users.view', $codes);
        $this->assertContains('users.manage_permissions', $codes);
        $sorted = $codes;
        sort($sorted);
        $this->assertSame($sorted, $codes);
    }

    public function test_reading_admin_role_permissions_requires_the_delegated_permission(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/roles/ADMIN/permissions', $headers)->assertForbidden();
    }

    public function test_delegated_admin_can_read_admin_role_permissions(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);

        $codes = $this->getJson('/api/roles/ADMIN/permissions', $headers)
            ->assertOk()
            ->json('data');

        // Le seed attribue users.view / users.manage au role ADMIN.
        $this->assertContains('users.view', $codes);
        $this->assertContains('users.manage', $codes);
        // Mais pas la permission delegable.
        $this->assertNotContains('users.manage_permissions', $codes);
    }

    public function test_super_admin_role_returns_the_whole_catalogue(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();

        $catalog = $this->getJson('/api/permissions', $headers)->json('data.*.code');
        $roleCodes = $this->getJson('/api/roles/SUPER_ADMIN/permissions', $headers)->assertOk()->json('data');

        sort($catalog);
        sort($roleCodes);
        $this->assertSame($catalog, $roleCodes);
    }

    public function test_unknown_role_is_a_404(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();

        $this->getJson('/api/roles/MANAGER/permissions', $headers)->assertNotFound();
        $this->putJson('/api/roles/MANAGER/permissions', ['permissions' => []], $headers)->assertNotFound();
    }

    public function test_super_admin_role_matrix_cannot_be_edited(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();

        $this->putJson('/api/roles/SUPER_ADMIN/permissions', ['permissions' => ['users.view']], $headers)
            ->assertStatus(422);
    }

    public function test_delegated_admin_can_replace_the_admin_role_permission_set(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);

        // On garde users.view (sinon l'appelant perdrait l'acces en lecture au
        // module) et on retire users.manage.
        $this->putJson('/api/roles/ADMIN/permissions', [
            'permissions' => ['users.view', 'clients.view'],
        ], $headers)->assertOk()->assertJsonPath('data', function ($data) {
            return in_array('users.view', $data, true)
                && in_array('clients.view', $data, true)
                && ! in_array('users.manage', $data, true);
        });

        $this->assertDatabaseMissing('permission_role', [
            'role' => 'ADMIN',
            'permission_id' => Permission::query()->where('code', 'users.manage')->value('id'),
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role' => 'ADMIN',
            'permission_id' => Permission::query()->where('code', 'clients.view')->value('id'),
        ]);
    }

    public function test_updating_a_role_matrix_rejects_unknown_codes(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);

        $this->putJson('/api/roles/ADMIN/permissions', [
            'permissions' => ['users.view', 'not.a.real.permission'],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('permissions.1');
    }

    public function test_updating_a_role_matrix_writes_an_audit_log_entry(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);

        $this->putJson('/api/roles/ADMIN/permissions', [
            'permissions' => ['users.view'],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.permissions_updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_individual_permissions_endpoint_accepts_permission_codes(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);
        $target = User::factory()->create();

        $this->putJson("/api/users/{$target->id}/permissions", [
            'permissions' => ['clients.view', 'clients.manage'],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('permission_user', [
            'user_id' => $target->id,
            'permission_id' => Permission::query()->where('code', 'clients.view')->value('id'),
        ]);
    }

    public function test_individual_permissions_endpoint_still_accepts_permission_ids(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);
        $target = User::factory()->create();
        $permissionId = Permission::query()->where('code', 'clients.view')->value('id');

        $this->putJson("/api/users/{$target->id}/permissions", [
            'permission_ids' => [$permissionId],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('permission_user', [
            'user_id' => $target->id,
            'permission_id' => $permissionId,
        ]);
    }

    public function test_individual_permissions_endpoint_rejects_an_empty_body(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);
        $target = User::factory()->create();

        $this->putJson("/api/users/{$target->id}/permissions", [], $headers)->assertStatus(422);
    }

    public function test_passing_an_empty_permissions_array_clears_individual_permissions(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->delegateManagePermissions($admin);
        $target = User::factory()->create();
        $permissionId = Permission::query()->where('code', 'clients.view')->value('id');
        DB::table('permission_user')->insert(['user_id' => $target->id, 'permission_id' => $permissionId]);

        $this->putJson("/api/users/{$target->id}/permissions", [
            'permissions' => [],
        ], $headers)->assertOk();

        $this->assertDatabaseMissing('permission_user', ['user_id' => $target->id]);
    }
}
