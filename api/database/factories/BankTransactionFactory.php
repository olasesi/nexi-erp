<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
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
            'bank_account_id' => BankAccount::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, -2000, 2000),
            'status' => 'cleared',
            'reconciliation_status' => 'unreconciled',
        ];
    }
}
