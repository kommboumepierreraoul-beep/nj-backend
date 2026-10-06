<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    private function actingAsSuperAdmin(array $attributes = []): array
    {
        $superAdmin = User::factory()->create(array_merge(['role' => UserRole::SUPER_ADMIN->value], $attributes));
        $token = $superAdmin->createAccessToken()['access_token'];

        return [$superAdmin, ['Authorization' => 'Bearer '.$token]];
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_admin_can_list_users_paginated(): void
    {
        [, $headers] = $this->actingAsAdmin();
        User::factory()->count(3)->create();

        $this->getJson('/api/users', $headers)
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_view_permission_is_required_to_list_users(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('users.view');

        $this->getJson('/api/users', $headers)->assertForbidden();
    }

    public function test_admin_can_update_a_users_email_but_it_must_stay_unique(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $userA = User::factory()->create(['email' => 'a@njglobaltrade.test']);
        $userB = User::factory()->create(['email' => 'b@njglobaltrade.test']);

        $this->putJson("/api/users/{$userA->id}", [
            'email' => 'a.new@njglobaltrade.test',
        ], $headers)->assertOk()->assertJsonPath('data.email', 'a.new@njglobaltrade.test');

        $this->putJson("/api/users/{$userA->id}", [
            'email' => 'b@njglobaltrade.test',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_updating_a_user_with_its_own_unchanged_email_does_not_fail_uniqueness(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'same@njglobaltrade.test']);

        $this->putJson("/api/users/{$user->id}", [
            'email' => 'same@njglobaltrade.test',
            'full_name' => 'Nom Mis A Jour',
        ], $headers)->assertOk()->assertJsonPath('data.full_name', 'Nom Mis A Jour');
    }

    public function test_only_a_super_admin_can_promote_a_user_to_super_admin(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->putJson("/api/users/{$user->id}", [
            'role' => UserRole::SUPER_ADMIN->value,
        ], $headers)->assertForbidden();
    }

    public function test_a_super_admin_can_promote_a_user_to_super_admin(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->putJson("/api/users/{$user->id}", [
            'role' => UserRole::SUPER_ADMIN->value,
        ], $headers)->assertOk()->assertJsonPath('data.role', UserRole::SUPER_ADMIN->value);
    }

    public function test_only_super_admin_can_change_a_users_status(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}/status", [
            'is_active' => false,
        ], $headers)->assertForbidden();
    }

    public function test_super_admin_can_deactivate_a_user_and_it_revokes_their_sessions(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();
        $user = User::factory()->create();
        $user->createAccessToken();

        $this->putJson("/api/users/{$user->id}/status", [
            'is_active' => false,
        ], $headers)->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_super_admin_cannot_change_their_own_status(): void
    {
        [$superAdmin, $headers] = $this->actingAsSuperAdmin();

        $this->putJson("/api/users/{$superAdmin->id}/status", [
            'is_active' => false,
        ], $headers)->assertStatus(422);
    }

    public function test_only_super_admin_can_hard_delete_a_user(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}", [], $headers)->assertForbidden();
    }

    public function test_super_admin_can_hard_delete_a_user_and_its_tokens_are_cleaned_up(): void
    {
        [, $headers] = $this->actingAsSuperAdmin();
        $user = User::factory()->create();
        $user->createAccessToken();

        $tokensBefore = PersonalAccessToken::query()->count();

        $this->deleteJson("/api/users/{$user->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        // Le jeton du user supprime a disparu, celui de l'acteur (super admin) subsiste.
        $this->assertSame($tokensBefore - 1, PersonalAccessToken::query()->count());
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        [$superAdmin, $headers] = $this->actingAsSuperAdmin();

        $this->deleteJson("/api/users/{$superAdmin->id}", [], $headers)->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_manage_permissions_permission_is_required_to_update_individual_permissions(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create();
        $permission = Permission::query()->create(['code' => 'billing.view', 'label' => 'Voir la facturation']);

        $this->putJson("/api/users/{$user->id}/permissions", [
            'permission_ids' => [$permission->id],
        ], $headers)->assertForbidden();
    }

    public function test_a_delegated_admin_can_update_individual_permissions(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $managePermissionsId = Permission::query()->where('code', 'users.manage_permissions')->value('id');
        DB::table('permission_user')->insert(['user_id' => $admin->id, 'permission_id' => $managePermissionsId]);

        $user = User::factory()->create();
        $permission = Permission::query()->create(['code' => 'billing.view', 'label' => 'Voir la facturation']);

        $this->putJson("/api/users/{$user->id}/permissions", [
            'permission_ids' => [$permission->id],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('permission_user', ['user_id' => $user->id, 'permission_id' => $permission->id]);
    }

    public function test_admin_can_force_logout_of_a_users_sessions(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create();
        $user->createAccessToken('device-a');
        $user->createAccessToken('device-b');

        $this->deleteJson("/api/users/{$user->id}/sessions", [], $headers)->assertOk();

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_user_can_list_and_revoke_their_own_sessions(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createAccessToken('device-a')['access_token'];
        $tokenARecord = PersonalAccessToken::query()->where('tokenable_id', $user->id)->where('name', 'device-a')->sole();
        $user->createAccessToken('device-b');

        $this->getJson('/api/auth/sessions', ['Authorization' => 'Bearer '.$tokenA])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->deleteJson("/api/auth/sessions/{$tokenARecord->id}", [], ['Authorization' => 'Bearer '.$tokenA])
            ->assertOk();

        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createAccessToken()['access_token'];

        $other = User::factory()->create();
        $other->createAccessToken();
        $otherTokenRecord = PersonalAccessToken::query()->where('tokenable_id', $other->id)->sole();

        $this->deleteJson("/api/auth/sessions/{$otherTokenRecord->id}", [], ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenRecord->id]);
    }

    public function test_sensitive_actions_are_recorded_in_the_audit_log(): void
    {
        [$superAdmin, $headers] = $this->actingAsSuperAdmin();
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}/status", ['is_active' => false], $headers)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $superAdmin->id,
            'action' => 'user.status_changed',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_creating_a_user_is_recorded_in_the_audit_log(): void
    {
        Notification::fake();

        [$admin, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/users', [
            'full_name' => 'Journalise Moi',
            'email' => 'journal@njglobaltrade.test',
            'role' => UserRole::ADMIN->value,
        ], $headers)->assertCreated();

        $createdUser = User::query()->where('email', 'journal@njglobaltrade.test')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user.created',
            'entity_type' => 'User',
            'entity_id' => $createdUser->id,
        ]);
    }

    public function test_an_unexpected_is_active_field_in_the_update_payload_has_no_effect(): void
    {
        // "role" et "is_active" ne sont pas mass-assignables sur le modele User (voir
        // User::class) : meme si un payload malveillant glisse "is_active" dans la
        // requete PUT /users/{user} (qui n'est pas censee le gerer, c'est le role de
        // PUT /users/{user}/status), il ne doit avoir absolument aucun effet.
        [, $headers] = $this->actingAsAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $this->putJson("/api/users/{$user->id}", [
            'full_name' => 'Nom Modifie',
            'is_active' => false,
        ], $headers)->assertOk();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_role_cannot_be_escalated_via_the_update_endpoint_without_the_super_admin_guard(): void
    {
        // Meme constat pour "role" : un ADMIN qui tente de s'auto-promouvoir (ou de
        // promouvoir un tiers) via PUT /users/{user} doit rester bloque par la garde
        // explicite du controleur, independamment du fonctionnement interne du modele.
        [$admin, $headers] = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", [
            'role' => UserRole::SUPER_ADMIN->value,
        ], $headers)->assertForbidden();

        $this->assertSame(UserRole::ADMIN->value, $admin->fresh()->role->value);
    }
}
