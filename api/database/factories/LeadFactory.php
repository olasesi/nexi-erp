<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => null,
            'assigned_to' => null,
            'name' => fake()->name(),
            'organization' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => fake()->randomElement(Lead::SOURCES),
            'status' => Lead::STATUS_NEW,
            'value' => fake()->randomFloat(2, 500, 50000),
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'next_follow_up_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_CONTACTED]);
    }

    public function qualified(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_QUALIFIED]);
    }

    public function converted(): static
    {
        return $this->state(fn () => [
            'status' => Lead::STATUS_CONVERTED,
            'converted_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => ['status' => Lead::STATUS_LOST]);
    }
}
