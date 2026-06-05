<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $taxRate = fake()->randomElement([0, 5, 10, 20]);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $discountAmount = fake()->optional(0.3)->randomFloat(2, 10, 500) ?? 0;
        $total = round($subtotal + $taxAmount - $discountAmount, 2);

        return [
            'company_id' => Company::factory(),
            'contact_id' => Contact::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_number' => 'PO-' . now()->format('Ymd') . '-' . fake()->unique()->numerify('#####'),
            'status' => OrderStatus::Draft->value,
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'paid_amount' => 0,
            'balance_due' => $total,
            'currency' => 'USD',
            'notes' => fake()->optional()->sentence(),
            'terms' => fake()->optional()->paragraph(),
            'order_date' => now(),
            'expected_date' => fake()->optional()->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => OrderStatus::Confirmed->value]);
    }
}
