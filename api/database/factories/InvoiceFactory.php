<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $tax = round($subtotal * 0.1, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'company_id' => Company::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'type' => 'invoice',
            'status' => 'draft',
            'contact_id' => Contact::factory(),
            'currency' => 'USD',
            'issue_date' => now()->subDays(rand(1, 30))->toDateString(),
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'balance_due' => $total,
        ];
    }
}
