<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CoworkingUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoworkingUnitFactory extends Factory
{
    protected $model = CoworkingUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'type' => fake()->randomElement(CoworkingUnit::TYPES),
            'capacity' => fake()->numberBetween(1, 10),
            'rate_hourly' => fake()->randomFloat(2, 5, 40),
            'rate_daily' => fake()->randomFloat(2, 25, 250),
            'is_active' => true,
        ];
    }

    public function desk(): static
    {
        return $this->state(fn () => [
            'type' => CoworkingUnit::TYPE_DESK,
            'capacity' => 1,
        ]);
    }
}
