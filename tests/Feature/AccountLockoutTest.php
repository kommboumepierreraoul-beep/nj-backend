<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_locks_after_reaching_the_max_failed_attempts(): void
    {
        $maxAttempts = (int) config('auth.login_lockout_max_attempts', 6);

        $user = User::factory()->create([
            'email' => 'lockout@njglobaltrade.test',
            'password' => 'Password!123',
        ]);

        // Chaque tentative individuelle reste un simple "identifiants invalides" (401),
        // y compris celle qui declenche le verrouillage : le compte n'est verrouille
        // qu'AU MOMENT ou le seuil est atteint, pas avant.
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lockout@njglobaltrade.test',
                'password' => 'WrongPassword!123',
            ])->assertUnauthorized();
        }

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isLockedOut());
        $this->assertSame(0, $fresh->failed_login_attempts);
    }

    public function test_a_locked_account_cannot_login_even_with_the_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'already-locked@njglobaltrade.test',
            'password' => 'Password!123',
        ]);
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'already-locked@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertStatus(423);
    }

    public function test_a_successful_login_resets_the_failed_attempts_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'recovers@njglobaltrade.test',
            'password' => 'Password!123',
        ]);
        $user->forceFill(['failed_login_attempts' => 3])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'recovers@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_lockout_expires_after_the_configured_duration(): void
    {
        $user = User::factory()->create([
            'email' => 'expired-lock@njglobaltrade.test',
            'password' => 'Password!123',
        ]);
        $user->forceFill(['locked_until' => now()->subMinute()])->save();

        $this->assertFalse($user->fresh()->isLockedOut());

        $this->postJson('/api/auth/login', [
            'email' => 'expired-lock@njglobaltrade.test',
            'password' => 'Password!123',
        ])->assertOk();
    }

    public function test_a_failed_attempt_on_an_unknown_email_does_not_error(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'ne-existe-pas@njglobaltrade.test',
            'password' => 'WrongPassword!123',
        ])->assertUnauthorized();
    }
}
