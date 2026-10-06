<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\SystemTrace;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = PersonalAccessToken::query()
            ->where('token', hash('sha256', $plainTextToken))
            ->first();

        if (! $accessToken) {
            SystemTrace::record('auth.token_invalid');

            return response()->json(['message' => 'Jeton invalide ou expire.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($accessToken->isExpired()) {
            SystemTrace::record('auth.token_expired', user: $accessToken->tokenable instanceof User ? $accessToken->tokenable : null);

            return response()->json(['message' => 'Jeton invalide ou expire.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $accessToken->tokenable instanceof User) {
            SystemTrace::record('auth.token_invalid');

            return response()->json(['message' => 'Jeton invalide ou expire.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $accessToken->tokenable;

        if (! $user->is_active) {
            SystemTrace::record('auth.account_disabled', user: $user);

            return response()->json(['message' => 'Compte desactive.'], Response::HTTP_FORBIDDEN);
        }

        // Expiration glissante : chaque requete authentifiee repousse l'expiration,
        // pour ne jamais deconnecter un utilisateur actif tout en faisant expirer
        // automatiquement un jeton compromis mais inutilise.
        $accessToken->forceFill([
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) config('auth.access_token_lifetime_days', 30)),
        ])->save();

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('access_token', $accessToken);

        return $next($request);
    }
}
