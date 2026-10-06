<?php

namespace Database\Factories;

use App\Enums\CommissionType;
use App\Models\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => fake()->words(3, true),
            'min_amount' => 0,
            'max_amount' => null,
            'commission_type' => CommissionType::POURCENTAGE->value,
            'rate_or_amount' => 10,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
