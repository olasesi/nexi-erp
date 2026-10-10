<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'task' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'is_billable' => false,
            'hourly_rate' => null,
            'created_by' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'started_at' => now(),
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
    }
}
