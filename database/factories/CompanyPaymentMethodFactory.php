<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\CompanyPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyPaymentMethod>
 */
class CompanyPaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
            'method_type' => fake()->randomElement(PaymentMethodType::cases())->value,
            'account_number' => fake()->optional()->bothify('### ### ###'),
            'account_holder' => fake()->optional()->name(),
            'iban' => null,
            'swift' => null,
            'instructions' => fake()->optional()->sentence(),
            'is_active' => true,
            'show_on_documents' => true,
            'sort_order' => 0,
        ];
    }
}
