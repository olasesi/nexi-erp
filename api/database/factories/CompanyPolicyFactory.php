<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyPolicyFactory extends Factory
{
    protected $model = CompanyPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'content' => fake()->paragraph(),
            'version' => '1.0',
            'effective_date' => now()->toDateString(),
            'status' => CompanyPolicy::STATUS_DRAFT,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => CompanyPolicy::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CompanyPolicy::STATUS_ARCHIVED]);
    }
}
