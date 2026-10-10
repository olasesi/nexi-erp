<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'budget_id' => Budget::factory(),
            'category' => ucfirst(fake()->word()),
            'type' => fake()->randomElement(BudgetLine::TYPES),
            'planned_amount' => fake()->randomFloat(2, 0, 5000),
            'actual_amount' => fake()->randomFloat(2, 0, 5000),
            'notes' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => BudgetLine::TYPE_INCOME]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => BudgetLine::TYPE_EXPENSE]);
    }
}
