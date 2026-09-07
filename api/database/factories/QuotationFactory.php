<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Quotation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);

        return [
            'company_id' => Company::factory(),
            'contact_id' => Contact::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quotation_no' => 'QT-'.fake()->unique()->numerify('######'),
            'status' => 'draft',
            'valid_until' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $subtotal,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent']);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'accepted']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }
}
