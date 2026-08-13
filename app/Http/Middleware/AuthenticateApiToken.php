<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
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

        if (! $accessToken || $accessToken->isExpired() || ! $accessToken->tokenable instanceof User) {
            return response()->json(['message' => 'Jeton invalide ou expire.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $accessToken->tokenable;

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte desactive.'], Response::HTTP_FORBIDDEN);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('access_token', $accessToken);

        return $next($request);
    }
}
