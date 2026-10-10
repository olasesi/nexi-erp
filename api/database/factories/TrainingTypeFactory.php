<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TrainingType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingTypeFactory extends Factory
{
    protected $model = TrainingType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)).' Training',
            'branch' => fake()->randomElement(['HQ', 'Downtown', 'Airport']),
            'department' => fake()->randomElement(['Operations', 'Sales', 'HR']),
            'description' => fake()->sentence(),
        ];
    }
}
