<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'due_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'status' => ProjectMilestone::STATUS_PENDING,
            'reached_at' => null,
            'created_by' => null,
        ];
    }

    public function reached(): static
    {
        return $this->state([
            'status' => ProjectMilestone::STATUS_REACHED,
            'reached_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => ProjectMilestone::STATUS_PENDING,
            'due_date' => now()->subWeek()->toDateString(),
        ]);
    }
}
