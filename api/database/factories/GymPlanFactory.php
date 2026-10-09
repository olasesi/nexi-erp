<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\GymPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class GymPlanFactory extends Factory
{
    protected $model = GymPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(2, true)).' Membership',
            'billing_period' => fake()->randomElement(GymPlan::PERIODS),
            'price' => fake()->randomFloat(2, 20, 200),
            'join_fee' => fake()->randomFloat(2, 0, 50),
            'max_monthly_visits' => null,
            'is_active' => true,
        ];
    }

    public function capped(int $visits): static
    {
        return $this->state(fn () => ['max_monthly_visits' => $visits]);
    }
}
