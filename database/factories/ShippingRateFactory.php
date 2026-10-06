<?php

namespace Database\Factories;

use App\Enums\ShippingMode;
use App\Models\ShippingRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
class ShippingRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mode' => ShippingMode::AERIEN->value,
            'min_quantity' => 0,
            'max_quantity' => null,
            'rate' => 5000,
            'unit' => 'kg',
            'lead_time_label' => '7 à 14 jours',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
