<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)).' Budget',
            'period_type' => fake()->randomElement(Budget::PERIODS),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => Budget::STATUS_DRAFT,
            'total_planned' => 0,
            'total_actual' => 0,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Budget::STATUS_ACTIVE]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => Budget::STATUS_CLOSED]);
    }
}
