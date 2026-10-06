<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_route_does_not_exist(): void
    {
        $this->postJson('/api/auth/register', [
            'full_name' => 'NJ Admin',
            'email' => 'admin@njglobaltrade.test',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertNotFound();
    }

    public function test_user_can_login_and_access_profile(): void
    {
        User::factory()->create([
            'email' => 'admin@njglobaltrade.test',
            'password' => 'Password!123',
            'full_name' => 'NJ Admin',
            'name' => 'NJ Admin',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertOk()->json('access_token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()->assertJsonPath('user.email', 'admin@njglobaltrade.test');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@njglobaltrade.test',
            'password' => 'Password!123',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertForbidden();
    }

    public function test_user_can_logout_current_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createAccessToken()['access_token'];

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_access_token_is_created_with_a_thirty_day_sliding_expiration(): void
    {
        $user = User::factory()->create();
        $user->createAccessToken();

        $token = PersonalAccessToken::query()->sole();

        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->greaterThanOrEqualTo(now()->addDays(29)));
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addDays(31)));
    }

    public function test_expired_token_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createAccessToken()['access_token'];

        PersonalAccessToken::query()->sole()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertUnauthorized();
    }

    public function test_active_use_slides_the_token_expiration_forward(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createAccessToken()['access_token'];

        PersonalAccessToken::query()->sole()->forceFill(['expires_at' => now()->addDay()])->save();

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        $refreshed = PersonalAccessToken::query()->sole();

        $this->assertTrue($refreshed->expires_at->greaterThanOrEqualTo(now()->addDays(29)));
        $this->assertTrue($refreshed->expires_at->lessThanOrEqualTo(now()->addDays(31)));
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'throttle@njglobaltrade.test',
            'password' => 'Password!123',
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'throttle@njglobaltrade.test',
                'password' => 'WrongPassword!123',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'throttle@njglobaltrade.test',
            'password' => 'WrongPassword!123',
        ])->assertStatus(429);
    }

    public function test_user_with_pending_password_change_cannot_access_protected_business_routes(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'must_change_password' => true,
        ]);
        $plainTextToken = $admin->createAccessToken()['access_token'];

        $this->postJson('/api/users', [
            'full_name' => 'Bloque Avant Changement',
            'email' => 'blocked@njglobaltrade.test',
            'role' => UserRole::ADMIN->value,
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@njglobaltrade.test']);
    }

    public function test_user_with_pending_password_change_can_still_reach_auth_routes(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $plainTextToken = $user->createAccessToken()['access_token'];

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword!123',
            'must_change_password' => true,
        ]);
        $plainTextToken = $user->createAccessToken()['access_token'];

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'OldPassword!123',
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_user_can_reset_password_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@njglobaltrade.test',
            'password' => 'OldPassword!123',
        ]);
        $user->createAccessToken();
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@njglobaltrade.test',
            'token' => $token,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_admin_can_create_user_and_send_invitation_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $plainTextToken = $admin->createAccessToken()['access_token'];

        $this->postJson('/api/users', [
            'full_name' => 'Nouveau Collaborateur',
            'email' => 'new.user@njglobaltrade.test',
            'role' => UserRole::ADMIN->value,
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertCreated()
            ->assertJsonPath('user.email', 'new.user@njglobaltrade.test')
            ->assertJsonPath('user.must_change_password', true);

        $createdUser = User::query()->where('email', 'new.user@njglobaltrade.test')->firstOrFail();

        $this->assertSame($admin->id, $createdUser->created_by_user_id);
        $this->assertFalse(Hash::check('Temporary!123', $createdUser->password));
        Notification::assertSentTo($createdUser, UserInvitationNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'new.user@njglobaltrade.test']);
    }

    public function test_invited_user_can_define_password_with_invitation_token(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $plainTextToken = $admin->createAccessToken()['access_token'];

        $this->postJson('/api/users', [
            'full_name' => 'Invite Secure',
            'email' => 'invite.secure@njglobaltrade.test',
            'role' => UserRole::ADMIN->value,
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertCreated();

        $createdUser = User::query()->where('email', 'invite.secure@njglobaltrade.test')->firstOrFail();
        $invitation = null;

        Notification::assertSentTo(
            $createdUser,
            UserInvitationNotification::class,
            function (UserInvitationNotification $notification) use (&$invitation): bool {
                $invitation = $notification;

                return true;
            },
        );

        $this->postJson('/api/auth/reset-password', [
            'email' => 'invite.secure@njglobaltrade.test',
            'token' => $invitation->token,
            'password' => 'SecurePassword!123',
            'password_confirmation' => 'SecurePassword!123',
        ])->assertOk();

        $this->assertTrue(Hash::check('SecurePassword!123', $createdUser->fresh()->password));
        $this->assertFalse($createdUser->fresh()->must_change_password);
    }

    public function test_admin_cannot_create_super_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $plainTextToken = $admin->createAccessToken()['access_token'];

        $this->postJson('/api/users', [
            'full_name' => 'Futur Super Admin',
            'email' => 'super.admin@njglobaltrade.test',
            'role' => UserRole::SUPER_ADMIN->value,
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'super.admin@njglobaltrade.test']);
    }

    public function test_user_permissions_can_be_direct_role_based_or_super_admin(): void
    {
        $directPermission = Permission::query()->create([
            'code' => 'users.read',
            'label' => 'Lire les utilisateurs',
        ]);
        $rolePermission = Permission::query()->create([
            'code' => 'users.write',
            'label' => 'Modifier les utilisateurs',
        ]);

        DB::table('permission_role')->insert([
            'role' => UserRole::ADMIN->value,
            'permission_id' => $rolePermission->id,
        ]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $admin->permissions()->attach($directPermission);

        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->assertTrue($admin->hasPermission('users.read'));
        $this->assertTrue($admin->hasPermission('users.write'));
        $this->assertFalse($admin->hasPermission('billing.delete'));
        $this->assertTrue($superAdmin->hasPermission('billing.delete'));
    }
}
