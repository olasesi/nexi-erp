<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\Company;
use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'reference' => strtoupper(fake()->bothify('COM-####')),
            'sale_amount' => 10000,
            'rate' => 5,
            'amount' => 500,
            'status' => Commission::STATUS_PENDING,
            'earned_on' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => Commission::STATUS_APPROVED]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Commission::STATUS_PAID]);
    }
}
