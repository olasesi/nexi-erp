<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PerformanceIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceIndicatorFactory extends Factory
{
    protected $model = PerformanceIndicator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'category' => fake()->randomElement(['Quality', 'Delivery', 'Teamwork']),
            'weight' => fake()->randomFloat(2, 1, 5),
            'target_value' => fake()->randomFloat(2, 0, 100),
            'unit' => '%',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
