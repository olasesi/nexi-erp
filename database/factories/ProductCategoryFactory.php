<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
            'company_id' => $parent->company_id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
