<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesAgent;
use App\Models\SalesAgentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesAgentOrderFactory extends Factory
{
    protected $model = SalesAgentOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sales_agent_id' => SalesAgent::factory(),
            'sales_order_id' => null,
            'order_number' => strtoupper(fake()->bothify('ORD-####')),
            'customer_name' => fake()->name(),
            'amount' => 1000,
            'commission_amount' => 50,
            'status' => SalesAgentOrder::STATUS_PENDING,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => SalesAgentOrder::STATUS_CONFIRMED]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => SalesAgentOrder::STATUS_DELIVERED]);
    }
}
