<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function authorizationUrl(): array
    {
        $state = Str::random(48);

        // Le state est stocke temporairement pour refuser les callbacks forges.
        Cache::put($this->cacheKey($state), true, now()->addMinutes(10));

        return [
            'url' => self::AUTH_URL.'?'.http_build_query([
                'client_id' => $this->clientId(),
                'redirect_uri' => $this->redirectUri(),
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'access_type' => 'online',
                'prompt' => 'select_account',
            ]),
            'state' => $state,
        ];
    }

    public function userFromCallback(string $code, string $state): array
    {
        $this->validateState($state);

        $token = $this->http()->asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        $accessToken = $token['access_token'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Google n a pas retourne de jeton d acces.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile = $this->http()
            ->withToken($accessToken)
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();

        $this->validateProfile($profile);

        return $profile;
    }

    private function validateState(string $state): void
    {
        if (! Cache::pull($this->cacheKey($state))) {
            throw new RuntimeException('Session Google invalide ou expiree.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function validateProfile(array $profile): void
    {
        if (empty($profile['sub']) || empty($profile['email'])) {
            throw new RuntimeException('Profil Google incomplet.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowedDomain = config('services.google.allowed_domain');

        if ($allowedDomain && (($profile['hd'] ?? null) !== $allowedDomain)) {
            throw new RuntimeException('Ce domaine Google n est pas autorise.', Response::HTTP_FORBIDDEN);
        }
    }

    private function cacheKey(string $state): string
    {
        return 'google_oauth_state:'.$state;
    }

    private function clientId(): string
    {
        return $this->requiredConfig('client_id');
    }

    private function clientSecret(): string
    {
        return $this->requiredConfig('client_secret');
    }

    private function redirectUri(): string
    {
        return $this->requiredConfig('redirect_uri');
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.google.'.$key);

        if (! $value) {
            throw new RuntimeException('Configuration Google manquante: '.$key, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return (string) $value;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(10);
    }
}
