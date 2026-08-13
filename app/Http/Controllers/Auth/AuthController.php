<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
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

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte desactive.'], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createAccessToken($validated['device_name'] ?? 'api-token');

        return response()->json([
            'message' => 'Connexion reussie.',
            'user' => new UserResource($user->load('permissions')),
            'access_token' => $token['access_token'],
            'token_type' => $token['token_type'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('permissions')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('access_token')?->delete();

        return response()->json(['message' => 'Deconnexion reussie.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
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
}
