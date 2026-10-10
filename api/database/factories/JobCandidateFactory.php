<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobCandidate;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobCandidateFactory extends Factory
{
    protected $model = JobCandidate::class;

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
            'phone' => fake()->phoneNumber(),
            'resume_url' => fake()->url(),
            'source' => fake()->randomElement(['website', 'referral', 'job_board', 'linkedin']),
            'stage' => JobCandidate::STAGE_APPLIED,
            'expected_salary' => fake()->randomFloat(2, 40000, 90000),
            'notes' => fake()->sentence(),
            'hired_at' => null,
            'created_by' => null,
        ];
    }

    public function hired(): static
    {
        return $this->state(fn () => [
            'stage' => JobCandidate::STAGE_HIRED,
            'hired_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'stage' => JobCandidate::STAGE_REJECTED,
        ]);
    }
}
