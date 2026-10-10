<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Training;
use App\Models\TrainingType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingFactory extends Factory
{
    protected $model = Training::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'training_type_id' => TrainingType::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'trainer' => fake()->name(),
            'branch' => fake()->randomElement(['HQ', 'Downtown', 'Airport']),
            'department' => fake()->randomElement(['Operations', 'Sales', 'HR']),
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'status' => Training::STATUS_SCHEDULED,
            'description' => fake()->sentence(),
            'created_by' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Training::STATUS_CANCELLED]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Training::STATUS_COMPLETED]);
    }
}
