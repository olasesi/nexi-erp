<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\SalesTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesTargetFactory extends Factory
{
    protected $model = SalesTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'period_type' => SalesTarget::PERIOD_MONTHLY,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_amount' => 10000,
            'achieved_amount' => 0,
            'status' => SalesTarget::STATUS_ACTIVE,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
