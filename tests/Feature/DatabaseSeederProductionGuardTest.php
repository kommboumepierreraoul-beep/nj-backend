<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_refuses_the_documented_default_admin_password_in_production(): void
    {
        config(['auth.default_admin.password' => 'ChangeMe!12345']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);

        (new DatabaseSeeder())->run();
    }

    public function test_seeder_accepts_a_custom_admin_password_in_production(): void
    {
        config(['auth.default_admin.password' => 'Sup3r$ecurePassword!Custom']);
        $this->app->detectEnvironment(fn () => 'production');

        (new DatabaseSeeder())->run();

        $this->assertDatabaseHas('users', ['email' => config('auth.default_admin.email')]);
    }

    public function test_seeder_accepts_the_default_password_outside_production(): void
    {
        config(['auth.default_admin.password' => 'ChangeMe!12345']);

        (new DatabaseSeeder())->run();

        $this->assertDatabaseHas('users', ['email' => config('auth.default_admin.email')]);
    }
}
