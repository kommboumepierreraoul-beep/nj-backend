<?php

namespace Database\Factories;

use App\Enums\SupplierReliability;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'province' => fake()->word(),
            'city' => fake()->city(),
            'reliability' => SupplierReliability::INCONNU->value,
            'is_verified' => false,
            'is_blacklisted' => false,
            'is_active' => true,
            'created_by_user_id' => User::factory(),
        ];
    }
}
