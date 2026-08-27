<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'category_id' => ProductCategory::factory(),
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'barcode' => fake()->unique()->ean13(),
            'type' => fake()->randomElement(ProductType::cases())->value,
            'unit' => fake()->randomElement(['pcs', 'kg', 'm', 'l', 'box', 'set']),
            'sale_price' => fake()->randomFloat(2, 10, 1000),
            'purchase_price' => fake()->randomFloat(2, 5, 800),
            'cost_price' => fake()->randomFloat(2, 3, 600),
            'tax_rate' => fake()->randomElement([0, 5, 10, 20]),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'min_stock_level' => fake()->numberBetween(5, 50),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state([
            'type' => ProductType::Service->value,
            'stock_quantity' => 0,
            'min_stock_level' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
