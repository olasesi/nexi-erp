<?php

namespace Database\Factories;

use App\Enums\PosOrderStatus;
use App\Models\Company;
use App\Models\PosOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosOrderFactory extends Factory
{
    protected $model = PosOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 500);

        return [
            'company_id' => Company::factory(),
            'register_id' => null,
            'contact_id' => null,
            'cashier_id' => null,
            'order_number' => 'POS-'.fake()->unique()->numerify('########'),
            'status' => PosOrderStatus::Completed,
            'payment_status' => PosOrder::PAYMENT_PAID,
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'discount_rate' => 0,
            'total' => $total,
            'paid_amount' => $total,
            'balance_due' => 0,
            'currency' => 'USD',
            'notes' => null,
            'order_date' => now()->toDateString(),
        ];
    }

    public function held(): static
    {
        return $this->state([
            'status' => PosOrderStatus::Held,
            'payment_status' => PosOrder::PAYMENT_PENDING,
            'paid_amount' => 0,
            'balance_due' => fn (array $attributes) => $attributes['total'],
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'status' => PosOrderStatus::Refunded,
            'payment_status' => PosOrder::PAYMENT_REFUNDED,
        ]);
    }
}
