<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceReviewFactory extends Factory
{
    protected $model = PerformanceReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => null,
            'reviewer_id' => null,
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'scores' => ['quality' => fake()->numberBetween(1, 5)],
            'overall_score' => fake()->randomFloat(2, 1, 5),
            'status' => PerformanceReview::STATUS_DRAFT,
            'comments' => fake()->sentence(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => PerformanceReview::STATUS_SUBMITTED]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => PerformanceReview::STATUS_APPROVED]);
    }
}
