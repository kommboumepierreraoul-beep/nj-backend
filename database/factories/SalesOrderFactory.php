<?php

namespace Database\Factories;

use App\Enums\CommissionType;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Models\Client;
use App\Models\Currency;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'NJG-'.fake()->unique()->numerify('####-####-###'),
            'client_id' => Client::factory(),
            'type' => SalesOrderType::MULTI_PRODUITS->value,
            'status' => SalesOrderStatus::BROUILLON->value,
            'currency_id' => Currency::factory(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'commission_type' => CommissionType::FORFAIT->value,
            'commission_amount' => 0,
            'total_amount' => 0,
            'order_date' => now()->toDateString(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
