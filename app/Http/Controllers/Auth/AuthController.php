<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SystemTrace;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        // Verrouillage de compte apres echecs repetes (audit, point 5.10) : verifie
        // avant meme la validation du mot de passe, pour bloquer aussi une tentative
        // avec le bon mot de passe pendant la periode de verrouillage.
        if ($user && $user->isLockedOut()) {
            SystemTrace::record('auth.login_locked', user: $user, emailAttempted: $validated['email']);

            return response()->json([
                'message' => sprintf(
                    'Compte temporairement verrouille suite a plusieurs echecs de connexion. Reessayez dans %d minute(s).',
                    (int) ceil($user->locked_until->diffInMinutes(now(), true))
                ),
            ], Response::HTTP_LOCKED);
        }

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            if ($user) {
                $this->registerFailedLoginAttempt($user);
            }

            SystemTrace::record('auth.login_failed', emailAttempted: $validated['email']);

            return response()->json(['message' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            SystemTrace::record('auth.login_blocked', user: $user, emailAttempted: $validated['email']);

            return response()->json(['message' => 'Compte desactive.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->failed_login_attempts > 0) {
            $user->forceFill(['failed_login_attempts' => 0])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createAccessToken($validated['device_name'] ?? 'api-token');

        SystemTrace::record('auth.login_succeeded', user: $user);

        return response()->json([
            'message' => 'Connexion reussie.',
            'user' => new UserResource($user->load('permissions')),
            'access_token' => $token['access_token'],
            'token_type' => $token['token_type'],
        ]);
    }

    /**
     * Compteur d'echecs par COMPTE (complementaire au rate limiting par IP deja en
     * place sur cette route via throttle:6,1) : survit a un changement d'adresse IP
     * de l'attaquant. Le seuil par defaut (6) est aligne sur le throttle IP pour ne
     * pas modifier le comportement observable du rate limiting existant.
     */
    private function registerFailedLoginAttempt(User $user): void
    {
        $maxAttempts = (int) config('auth.login_lockout_max_attempts', 6);
        $attempts = $user->failed_login_attempts + 1;

        if ($attempts >= $maxAttempts) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes((int) config('auth.login_lockout_duration_minutes', 15)),
            ])->save();

            SystemTrace::record('auth.account_locked', user: $user);

            return;
        }

        $user->forceFill(['failed_login_attempts' => $attempts])->save();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('permissions')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        SystemTrace::record('auth.logout', user: $request->user());

        $request->attributes->get('access_token')?->delete();

        return response()->json(['message' => 'Deconnexion reussie.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        SystemTrace::record('auth.logout_all', user: $request->user());

        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Toutes les sessions ont ete fermees.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->user()->tokens()
            ->whereKeyNot($request->attributes->get('access_token')?->getKey())
            ->delete();

        return response()->json(['message' => 'Mot de passe modifie avec succes.']);
    }

    /**
     * Page B1 "Mon compte / Securite & sessions" : liste des sessions (jetons
     * d'acces) actives de l'utilisateur connecte, avec indication de la session
     * courante.
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->attributes->get('access_token')?->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ]);

        return response()->json(['data' => $sessions]);
    }

    /**
     * Revoque une session precise appartenant a l'utilisateur connecte (jamais
     * celle d'un autre utilisateur : voir UserManagementController::revokeSessions
     * pour la deconnexion forcee d'un tiers par un administrateur).
     */
    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->first();

        if (! $token) {
            return response()->json(['message' => 'Session introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $token->delete();

        SystemTrace::record('auth.session_revoked_self', user: $request->user(), context: ['token_id' => $tokenId]);

        return response()->json(['message' => 'Session revoquee.']);
    }
}
