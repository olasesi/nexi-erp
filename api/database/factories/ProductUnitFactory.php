<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->word(),
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'base_unit' => null,
            'operator' => ProductUnit::OPERATOR_MULTIPLY,
            'operation_value' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function divide(): static
    {
        return $this->state(['operator' => ProductUnit::OPERATOR_DIVIDE]);
    }
}
