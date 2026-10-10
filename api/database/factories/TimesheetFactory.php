<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'status' => Timesheet::STATUS_DRAFT,
            'total_hours' => 0,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => Timesheet::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Timesheet::STATUS_APPROVED,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }
}
