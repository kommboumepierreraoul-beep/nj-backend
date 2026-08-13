<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\NewUserCredentialsNotification;
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

    public function test_admin_can_create_user_and_send_credentials_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $plainTextToken = $admin->createAccessToken()['access_token'];

        $this->postJson('/api/users', [
            'full_name' => 'Nouveau Collaborateur',
            'email' => 'new.user@njglobaltrade.test',
            'role' => UserRole::ADMIN->value,
            'password' => 'Temporary!123',
            'password_confirmation' => 'Temporary!123',
        ], [
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertCreated()
            ->assertJsonPath('user.email', 'new.user@njglobaltrade.test')
            ->assertJsonPath('user.must_change_password', true);

        $createdUser = User::query()->where('email', 'new.user@njglobaltrade.test')->firstOrFail();

        $this->assertSame($admin->id, $createdUser->created_by_user_id);
        $this->assertTrue(Hash::check('Temporary!123', $createdUser->password));
        Notification::assertSentTo($createdUser, NewUserCredentialsNotification::class);
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
            'password' => 'Temporary!123',
            'password_confirmation' => 'Temporary!123',
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
