<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(GoogleOAuthService $google): JsonResponse
    {
        return response()->json($google->authorizationUrl());
    }

    public function callback(Request $request, GoogleOAuthService $google): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            $profile = $google->userFromCallback($validated['code'], $validated['state']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getCode() ?: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Authentification Google impossible pour le moment.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        // On priorise la correspondance par google_id : une fois qu'un compte a ete
        // lie a un identifiant Google, c'est cette liaison qui fait foi, meme si
        // l'email interne a ensuite change (voir decision #1, section 10 de
        // Doc/spec_pages_utilisateurs.md, sur la mutabilite de l'email). On ne
        // retombe sur une recherche par email que pour la toute premiere connexion
        // Google d'un compte pas encore lie.
        $user = User::query()->where('google_id', $profile['sub'])->first()
            ?? User::query()->where('email', $profile['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Aucun compte interne ne correspond a cet email Google.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte desactive.'], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill([
            'google_id' => $user->google_id ?: $profile['sub'],
            'avatar_url' => $profile['picture'] ?? $user->avatar_url,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'last_login_at' => now(),
        ])->save();

        $token = $user->createAccessToken($validated['device_name'] ?? 'google-oauth');

        return response()->json([
            'message' => 'Connexion Google reussie.',
            'user' => new UserResource($user->load('permissions')),
            'access_token' => $token['access_token'],
            'token_type' => $token['token_type'],
        ]);
    }
}
