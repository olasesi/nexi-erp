<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\HrmLeave;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrmLeaveFactory extends Factory
{
    protected $model = HrmLeave::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'leave_type' => fake()->randomElement(HrmLeave::TYPES),
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'days' => 1,
            'status' => HrmLeave::STATUS_PENDING,
            'reason' => fake()->sentence(),
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => HrmLeave::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => HrmLeave::STATUS_REJECTED,
            'review_note' => 'Clashing with the release window.',
            'reviewed_at' => now(),
        ]);
    }

    public function spanning(string $start, string $end, float $days): static
    {
        return $this->state(['start_date' => $start, 'end_date' => $end, 'days' => $days]);
    }
}
