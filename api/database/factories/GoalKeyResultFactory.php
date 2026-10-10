<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalKeyResultFactory extends Factory
{
    protected $model = GoalKeyResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'goal_id' => Goal::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'target_value' => 100,
            'current_value' => 0,
            'unit' => null,
            'progress' => 0,
            'status' => GoalKeyResult::STATUS_ACTIVE,
        ];
    }
}
