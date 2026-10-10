<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Goal;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(Goal::TYPES),
            'category' => null,
            'owner_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => Goal::STATUS_DRAFT,
            'progress' => 0,
            'target_value' => 100,
            'current_value' => 0,
            'unit' => null,
            'created_by' => null,
        ];
    }
}
