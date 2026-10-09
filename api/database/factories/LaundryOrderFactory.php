<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\LaundryOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaundryOrderFactory extends Factory
{
    protected $model = LaundryOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'order_number' => sprintf('LW-%s-%04d', now()->format('ym'), fake()->unique()->numberBetween(1, 9999)),
            'contact_id' => Contact::factory(),
            'status' => LaundryOrder::STATUS_RECEIVED,
            'requested_at' => now()->addDays(2)->toDateString(),
            'completed_at' => null,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function washing(): static
    {
        return $this->state(fn () => ['status' => LaundryOrder::STATUS_WASHING]);
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => LaundryOrder::STATUS_READY]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => LaundryOrder::STATUS_DELIVERED,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => LaundryOrder::STATUS_CANCELLED]);
    }
}
