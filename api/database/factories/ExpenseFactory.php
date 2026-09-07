<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'reference_no' => 'EXP-'.fake()->unique()->numerify('######'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'date' => now()->subDays(rand(1, 30)),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'credit_card']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
