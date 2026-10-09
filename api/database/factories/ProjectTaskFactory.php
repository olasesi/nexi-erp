<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTaskFactory extends Factory
{
    protected $model = ProjectTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'assigned_to' => null,
            'status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_MEDIUM,
            'due_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => ProjectTask::STATUS_IN_PROGRESS]);
    }

    public function done(): static
    {
        return $this->state([
            'status' => ProjectTask::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subWeek()->toDateString(),
        ]);
    }
}
