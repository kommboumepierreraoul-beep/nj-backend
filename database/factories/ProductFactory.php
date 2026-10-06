<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => ProductCategory::factory(),
            'reference' => 'REF-'.fake()->unique()->numerify('######'),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 999999),
            'description' => fake()->sentence(),
            'status' => ProductStatus::ACTIVE->value,
            'is_sensitive' => false,
            'min_order_quantity' => fake()->numberBetween(1, 100),
            'brand' => fake()->company(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
