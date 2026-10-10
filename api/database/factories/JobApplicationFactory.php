<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'job_posting_id' => JobPosting::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'status' => JobApplication::STATUS_RECEIVED,
            'created_by' => null,
        ];
    }

    public function shortlisted(): static
    {
        return $this->state(fn () => ['status' => JobApplication::STATUS_SHORTLISTED]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => JobApplication::STATUS_REJECTED]);
    }
}
