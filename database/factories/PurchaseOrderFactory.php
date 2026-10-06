<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'PO-'.fake()->unique()->numerify('########'),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrderStatus::DRAFT->value,
            'order_date' => now()->toDateString(),
            'total_amount' => 0,
            'currency_id' => Currency::factory(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
