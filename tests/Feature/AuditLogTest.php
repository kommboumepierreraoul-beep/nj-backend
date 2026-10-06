<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    public function test_view_permission_is_required_to_list_audit_logs(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('audit_logs.view');

        $this->getJson('/api/audit-logs', $headers)->assertForbidden();
    }

    public function test_admin_can_list_audit_logs_with_actor_loaded(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $target = User::factory()->create();

        AuditLog::record('user.updated', $target, $admin, ['full_name' => 'Ancien'], ['full_name' => 'Nouveau']);

        $this->getJson('/api/audit-logs', $headers)
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']])
            ->assertJsonPath('data.0.action', 'user.updated')
            ->assertJsonPath('data.0.actor.id', $admin->id);
    }

    public function test_audit_logs_can_be_filtered_by_entity_and_action(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        AuditLog::record('user.updated', $userA, $admin);
        AuditLog::record('user.deleted', $userB, $admin);

        $this->getJson("/api/audit-logs?entity_id={$userA->id}&action=user.updated", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity_id', $userA->id);
    }

    public function test_sensitive_user_actions_capture_ip_address(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $headers = ['Authorization' => 'Bearer '.$superAdmin->createAccessToken()['access_token']];
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}/status", ['is_active' => false], $headers)->assertOk();

        $log = AuditLog::query()->where('action', 'user.status_changed')->sole();

        $this->assertNotNull($log->ip_address);
    }
}
