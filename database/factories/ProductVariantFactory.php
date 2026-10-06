<?php

namespace Database\Factories;

use App\Enums\VariantLevel;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('########'),
            'name' => fake()->words(2, true),
            'level' => VariantLevel::STANDARD->value,
            'purchase_price' => fake()->randomFloat(2, 1, 500),
            'purchase_currency_id' => Currency::factory(),
            'moq' => fake()->numberBetween(1, 500),
            'is_recommended' => false,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
