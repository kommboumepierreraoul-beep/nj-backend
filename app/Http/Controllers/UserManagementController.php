<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('permissions')
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->input('search').'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', $search)->orWhere('email', 'like', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        if ($validated['role'] === UserRole::SUPER_ADMIN->value && ! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Seul un super administrateur peut creer un super administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Le mot de passe n'est jamais transmis par email: l'utilisateur le definit via le lien d'invitation.
        // "role" et "is_active" ne sont pas mass-assignables (voir User::class) : ecrits
        // explicitement via forceFill() une fois la ligne creee, jamais depuis un tableau
        // fourni tel quel par la requete.
        $user = User::query()->create([
            'name' => $validated['full_name'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Str::random(64),
            'must_change_password' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        $user->forceFill([
            'role' => $validated['role'],
            'is_active' => true,
        ])->save();

        // Le renvoi d'invitation (page C1/C2) reutilise ce meme mecanisme forgot-password
        // (voir Doc/spec_pages_utilisateurs.md section 10, decision #4).
        $user->notify(new UserInvitationNotification(
            token: PasswordBroker::createToken($user),
            platformUrl: (string) config('app.frontend_url'),
        ));

        AuditLog::record(
            action: 'user.created',
            entity: $user,
            actor: $request->user(),
            old: null,
            new: $user->only(['full_name', 'email', 'role']),
        );

        return response()->json([
            'message' => 'Utilisateur cree et invitation envoyee.',
            'user' => new UserResource($user->load('permissions')),
        ], Response::HTTP_CREATED);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($user->load('permissions')),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            // L'email reste unique globalement mais est desormais modifiable (decision #1) :
            // on ignore la propre ligne de l'utilisateur pour ne pas se bloquer soi-meme.
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', new Enum(UserRole::class)],
        ]);

        $currentRole = $user->role?->value ?? $user->role;
        $roleChanging = isset($validated['role']) && $validated['role'] !== $currentRole;
        $involvesSuperAdmin = $roleChanging && ($validated['role'] === UserRole::SUPER_ADMIN->value || $currentRole === UserRole::SUPER_ADMIN->value);

        if ($involvesSuperAdmin && ! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Seul un super administrateur peut attribuer ou retirer le role super administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($roleChanging && $currentRole === UserRole::SUPER_ADMIN->value && $validated['role'] !== UserRole::SUPER_ADMIN->value
            && $this->isLastActiveSuperAdmin($user)) {
            return response()->json([
                'message' => 'Impossible de retirer le role super administrateur du dernier super administrateur actif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $emailChanging = isset($validated['email']) && $validated['email'] !== $user->email;
        $old = $user->only(['full_name', 'email', 'role']);

        // "role" n'est pas mass-assignable (voir User::class) : on l'ecrit a part via
        // forceFill(), une fois les gardes ci-dessus passees, plutot que de le laisser
        // transiter tel quel dans $validated.
        $role = $validated['role'] ?? null;
        unset($validated['role']);

        $user->update($validated);

        if ($role !== null) {
            $user->forceFill(['role' => $role])->save();
        }

        AuditLog::record(
            action: 'user.updated',
            entity: $user,
            actor: $request->user(),
            old: $old,
            new: $user->only(['full_name', 'email', 'role']),
        );

        return response()->json([
            'message' => $emailChanging
                ? "Utilisateur mis a jour. L'email a change : si ce compte se connecte via Google, verifiez qu'il correspond bien au compte Google utilise."
                : 'Utilisateur mis a jour.',
            'data' => new UserResource($user->load('permissions')),
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        // Decision #2 (Doc/spec_pages_utilisateurs.md section 10) : l'activation et la
        // desactivation d'un compte passent systematiquement par le consentement du
        // super administrateur.
        if (! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Seul un super administrateur peut activer ou desactiver un utilisateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre statut.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $validated['is_active'] && $this->isLastActiveSuperAdmin($user)) {
            return response()->json([
                'message' => 'Impossible de desactiver le dernier super administrateur actif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // "is_active" n'est pas mass-assignable (voir User::class) : forceFill() explicite.
        $old = ['is_active' => $user->is_active];
        $user->forceFill(['is_active' => $validated['is_active']])->save();

        if (! $user->is_active) {
            // La desactivation revoque immediatement toutes les sessions actives.
            $user->tokens()->delete();
        }

        AuditLog::record('user.status_changed', $user, $request->user(), $old, ['is_active' => $user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Utilisateur reactive.' : 'Utilisateur desactive.',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        // Decision #2 : la suppression definitive, comme la desactivation, est
        // reservee au super administrateur.
        if (! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Seul un super administrateur peut supprimer definitivement un utilisateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            return response()->json([
                'message' => 'Impossible de supprimer le dernier super administrateur actif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        AuditLog::record(
            action: 'user.deleted',
            entity: $user,
            actor: $request->user(),
            old: $user->only(['id', 'full_name', 'email', 'role']),
            new: null,
        );

        // personal_access_tokens utilise une relation polymorphe (morphs) sans contrainte
        // de cle etrangere reelle (voir migration create_personal_access_tokens_table) :
        // il n'y a donc pas de suppression en cascade automatique, il faut la faire ici.
        // Les tables permission_user (cascadeOnDelete) et users.created_by_user_id
        // (nullOnDelete) sont, elles, deja gerees par une contrainte FK reelle.
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprime definitivement.']);
    }

    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        // Deux formes acceptees pour le corps : `permission_ids` (entiers, forme
        // historique) ou `permissions` (codes `module.action`, forme utilisee
        // par le frontend et coherente avec le reste du module). L'une des deux
        // doit etre presente (tableau vide = retrait de toutes les permissions
        // individuelles).
        $request->validate([
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        if (! $request->has('permission_ids') && ! $request->has('permissions')) {
            return response()->json([
                'message' => 'Corps invalide : fournir `permissions` (codes) ou `permission_ids`.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $permissionIds = $request->has('permissions')
            ? Permission::query()->whereIn('code', $request->input('permissions', []))->pluck('id')->all()
            : $request->input('permission_ids', []);

        $old = $user->permissions()->pluck('code')->all();

        $user->permissions()->sync($permissionIds);

        $new = $user->permissions()->pluck('code')->all();

        AuditLog::record(
            action: 'user.permissions_updated',
            entity: $user,
            actor: $request->user(),
            old: ['permissions' => $old],
            new: ['permissions' => $new],
        );

        return response()->json([
            'message' => 'Permissions individuelles mises a jour.',
            'data' => new UserResource($user->load('permissions')),
        ]);
    }

    public function revokeSessions(Request $request, User $user): JsonResponse
    {
        $revokedCount = $user->tokens()->count();
        $user->tokens()->delete();

        AuditLog::record(
            action: 'user.sessions_revoked',
            entity: $user,
            actor: $request->user(),
            old: null,
            new: ['revoked_count' => $revokedCount],
        );

        return response()->json(['message' => "Les sessions actives de l'utilisateur ont ete revoquees."]);
    }

    /**
     * Garde-fou (a valider - voir Doc/spec_pages_utilisateurs.md section 11, point 7) :
     * empeche de desactiver, retrograder ou supprimer le dernier super administrateur
     * encore actif, afin de ne jamais laisser la plateforme sans super administrateur.
     */
    private function isLastActiveSuperAdmin(User $user): bool
    {
        if (($user->role?->value ?? $user->role) !== UserRole::SUPER_ADMIN->value || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
