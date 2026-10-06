<?php

namespace Database\Factories;

use App\Enums\UnitType;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('UNIT-????'),
            'label' => fake()->word(),
            'type' => UnitType::PIECE->value,
        ];
    }
}
