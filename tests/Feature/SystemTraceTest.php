<?php

namespace Tests\Feature;

use App\Models\SystemTrace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SystemTraceTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_successful_login_is_traced(): void
    {
        $user = User::factory()->create([
            'email' => 'trace.success@njglobaltrade.test',
            'password' => 'Password!123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'trace.success@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertOk();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => $user->id,
            'event' => 'auth.login_succeeded',
        ]);
    }

    public function test_failed_login_is_traced_with_attempted_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown@njglobaltrade.test',
            'password' => 'WrongPassword!123',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => null,
            'event' => 'auth.login_failed',
            'email_attempted' => 'unknown@njglobaltrade.test',
        ]);
    }

    public function test_inactive_account_login_attempt_is_traced(): void
    {
        $user = User::factory()->create([
            'email' => 'trace.inactive@njglobaltrade.test',
            'password' => 'Password!123',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'trace.inactive@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertForbidden();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => $user->id,
            'event' => 'auth.login_blocked',
        ]);
    }

    public function test_logout_is_traced(): void
    {
        $user = User::factory()->create();
        $token = $user->createAccessToken()['access_token'];

        $this->postJson('/api/auth/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => $user->id,
            'event' => 'auth.logout',
        ]);
    }

    public function test_invalid_token_is_traced(): void
    {
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer bogus-token'])->assertUnauthorized();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => null,
            'event' => 'auth.token_invalid',
        ]);
    }

    public function test_permission_denial_is_traced(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('users.view');

        $this->getJson('/api/users', $headers)->assertForbidden();

        $this->assertDatabaseHas('system_traces', [
            'user_id' => $admin->id,
            'event' => 'auth.permission_denied',
        ]);
    }

    public function test_guest_cannot_list_system_traces(): void
    {
        $this->getJson('/api/system-traces')->assertUnauthorized();
    }

    public function test_view_permission_is_required_to_list_system_traces(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('system_traces.view');

        $this->getJson('/api/system-traces', $headers)->assertForbidden();
    }

    public function test_admin_can_list_system_traces(): void
    {
        [, $headers] = $this->actingAsAdmin();
        SystemTrace::record('auth.login_failed', emailAttempted: 'someone@njglobaltrade.test');

        $this->getJson('/api/system-traces', $headers)
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']])
            ->assertJsonPath('data.0.event', 'auth.login_failed');
    }
}
