<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'company_id' => Company::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
            'status' => ProjectStatus::Planning,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+3 months')->format('Y-m-d'),
            'budget' => fake()->randomFloat(2, 1000, 250000),
            'owner_id' => User::factory(),
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProjectStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ProjectStatus::Completed]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => ProjectStatus::Active,
            'start_date' => now()->subMonths(4)->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ]);
    }
}
