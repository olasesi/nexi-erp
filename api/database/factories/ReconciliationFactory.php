<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Reconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reconciliation>
 */
class ReconciliationFactory extends Factory
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
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'opening_balance' => 0,
            'closing_balance' => 0,
            'status' => 'open',
        ];
    }
}
