<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleProfile(array $overrides = []): array
    {
        return array_merge([
            'sub' => 'google-sub-123',
            'email' => 'user@njglobaltrade.test',
            'picture' => 'https://example.test/avatar.png',
        ], $overrides);
    }

    private function mockGoogleCallback(array $profile): void
    {
        $this->mock(GoogleOAuthService::class, function ($mock) use ($profile): void {
            $mock->shouldReceive('userFromCallback')->once()->andReturn($profile);
        });
    }

    public function test_google_login_matches_an_existing_account_by_google_id_even_if_the_email_has_changed_since(): void
    {
        // Le compte a deja ete lie a Google lors d'une connexion precedente, puis
        // son email interne a change (ex: passage d'un gmail au domaine
        // nj-global-trade, voir decision #1, section 10 de
        // Doc/spec_pages_utilisateurs.md). La correspondance doit continuer a se
        // faire via google_id, pas via l'email desormais different de celui du
        // profil Google.
        $user = User::factory()->create([
            'email' => 'nouvel.email@njglobaltrade.test',
            'google_id' => 'google-sub-123',
        ]);

        $this->mockGoogleCallback($this->fakeGoogleProfile(['email' => 'ancien.email@gmail.com']));

        $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code',
            'state' => 'state-value',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_google_login_falls_back_to_email_lookup_for_a_first_time_google_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@njglobaltrade.test',
            'google_id' => null,
        ]);

        $this->mockGoogleCallback($this->fakeGoogleProfile());

        $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code',
            'state' => 'state-value',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertSame('google-sub-123', $user->fresh()->google_id);
    }

    public function test_google_login_is_refused_when_no_account_matches(): void
    {
        $this->mockGoogleCallback($this->fakeGoogleProfile(['email' => 'inconnu@njglobaltrade.test']));

        $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code',
            'state' => 'state-value',
        ])->assertForbidden();
    }
}
