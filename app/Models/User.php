<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ApiResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'full_name', 'email', 'password', 'role', 'is_active', 'last_login_at', 'must_change_password', 'created_by_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function createAccessToken(string $name = 'api-token', ?array $abilities = null): array
    {
        $plainTextToken = Str::random(64);

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
        ]);

        return [
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'token' => $token,
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ApiResetPasswordNotification($token, $this->email));
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = $role instanceof UserRole ? $role->value : $role;

        return ($this->role?->value ?? $this->role) === $role;
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->hasRole(UserRole::SUPER_ADMIN)) {
            return true;
        }

        $role = $this->role?->value ?? $this->role;

        return $this->permissions()
            ->where('code', $permissionCode)
            ->exists()
            || DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role', $role)
                ->where('permissions.code', $permissionCode)
                ->exists();
    }
}
