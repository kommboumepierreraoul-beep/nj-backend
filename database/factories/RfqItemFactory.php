<?php

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rfq_id' => Rfq::factory(),
            'custom_description' => fake()->sentence(),
            'target_quantity' => fake()->numberBetween(10, 1000),
        ];
    }
}
