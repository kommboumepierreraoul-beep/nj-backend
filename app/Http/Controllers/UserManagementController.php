<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\NewUserCredentialsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class UserManagementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', new Enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validated['role'] === UserRole::SUPER_ADMIN->value && ! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Seul un super administrateur peut creer un super administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Les comptes sont internes: ils naissent actifs, mais doivent changer le mot de passe temporaire.
        $user = User::query()->create([
            'name' => $validated['full_name'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => true,
            'must_change_password' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        $user->notify(new NewUserCredentialsNotification(
            temporaryPassword: $validated['password'],
            platformUrl: (string) config('app.frontend_url'),
        ));

        return response()->json([
            'message' => 'Utilisateur cree et notification envoyee.',
            'user' => new UserResource($user->load('permissions')),
        ], Response::HTTP_CREATED);
    }
}
