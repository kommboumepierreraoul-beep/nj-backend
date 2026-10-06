<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->word(),
            'symbol' => fake()->randomElement(['¥', '$', 'FCFA', '€']),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
