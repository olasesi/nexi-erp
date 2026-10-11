<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesAgentFactory extends Factory
{
    protected $model = SalesAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => null,
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'commission_rate' => 5,
            'status' => SalesAgent::STATUS_PENDING,
            'requested_at' => null,
            'approved_at' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => SalesAgent::STATUS_ACTIVE]);
    }

    public function requested(): static
    {
        return $this->state(fn () => ['status' => SalesAgent::STATUS_REQUESTED]);
    }
}
