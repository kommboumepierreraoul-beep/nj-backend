<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = config('auth.default_admin');

        Validator::make($admin, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', new Enum(UserRole::class)],
        ])->validate();

        // Ce compte sert uniquement a l'amorcage du systeme: il n'est pas recree si l'email existe deja.
        User::query()->firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'full_name' => $admin['name'],
                'password' => $admin['password'],
                'role' => $admin['role'],
                'is_active' => true,
                'must_change_password' => true,
            ],
        );
    }
}
