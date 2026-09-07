<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'payment_number' => 'REC-'.fake()->unique()->numerify('########'),
            'type' => 'receipt',
            'contact_id' => Contact::factory(),
            'invoice_id' => null,
            'payment_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'method' => 'bank_transfer',
            'status' => 'completed',
            'reference' => fake()->bothify('REF-####'),
        ];
    }
}
