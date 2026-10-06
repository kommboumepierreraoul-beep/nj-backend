<?php

namespace Database\Factories;

use App\Enums\RFQStatus;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'RFQ-'.fake()->unique()->numerify('########'),
            'requested_by_user_id' => User::factory(),
            'status' => RFQStatus::BROUILLON->value,
            'request_date' => now()->toDateString(),
            'notes' => fake()->sentence(),
        ];
    }
}
