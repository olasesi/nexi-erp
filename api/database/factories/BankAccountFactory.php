<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Checking', 'Savings']),
            'bank_name' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'account_type' => 'checking',
            'currency' => 'USD',
            'opening_balance' => fake()->randomFloat(2, 0, 50000),
            'is_active' => true,
        ];
    }
}
