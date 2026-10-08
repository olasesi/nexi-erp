<?php

namespace Database\Factories;

use App\Models\BusinessHealthSnapshot;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessHealthSnapshotFactory extends Factory
{
    protected $model = BusinessHealthSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'score_date' => now()->toDateString(),
            'overall_score' => fake()->numberBetween(0, 100),
            'financial_score' => fake()->numberBetween(0, 100),
            'sales_score' => fake()->numberBetween(0, 100),
            'operations_score' => fake()->numberBetween(0, 100),
            'team_score' => fake()->numberBetween(0, 100),
            'projects_score' => null,
            'signals' => [],
            'alerts' => [],
            'insights' => [],
            'recommendations' => [],
            'generated_by' => 'rules',
        ];
    }
}
