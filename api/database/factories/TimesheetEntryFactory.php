<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetEntryFactory extends Factory
{
    protected $model = TimesheetEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'timesheet_id' => Timesheet::factory(),
            'work_date' => now()->toDateString(),
            'hours' => 8,
            'project' => null,
            'task' => null,
            'notes' => null,
        ];
    }
}
