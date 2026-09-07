<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'income_category_id' => IncomeCategory::factory(),
            'reference_no' => 'INC-'.fake()->unique()->numerify('######'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'date' => now()->subDays(rand(1, 30)),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'credit_card']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
