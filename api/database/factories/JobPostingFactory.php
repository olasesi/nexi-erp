<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'department' => fake()->randomElement(['Operations', 'Sales', 'Engineering', 'HR']),
            'location' => fake()->city(),
            'employment_type' => fake()->randomElement(JobPosting::EMPLOYMENT_TYPES),
            'description' => fake()->paragraph(),
            'requirements' => fake()->paragraph(),
            'salary_min' => fake()->randomFloat(2, 30000, 60000),
            'salary_max' => fake()->randomFloat(2, 60000, 120000),
            'openings' => 1,
            'status' => JobPosting::STATUS_DRAFT,
            'published_at' => null,
            'closing_date' => now()->addMonth()->toDateString(),
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => JobPosting::STATUS_CLOSED,
            'published_at' => now()->subWeek(),
        ]);
    }
}
