<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmAttendance;
use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrmAttendanceFactory extends Factory
{
    protected $model = HrmAttendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'attendance_date' => now()->toDateString(),
            'status' => HrmAttendance::STATUS_PRESENT,
            'check_in' => '09:00',
            'check_out' => '17:00',
            'work_hours' => 8,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state([
            'status' => HrmAttendance::STATUS_ABSENT,
            'check_in' => null,
            'check_out' => null,
            'work_hours' => 0,
        ]);
    }

    public function late(): static
    {
        return $this->state([
            'status' => HrmAttendance::STATUS_LATE,
            'check_in' => '10:30',
            'work_hours' => 6.5,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(['attendance_date' => $date]);
    }
}
