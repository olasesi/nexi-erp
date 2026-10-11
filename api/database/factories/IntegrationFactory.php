<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'provider' => fake()->unique()->slug(2),
            'status' => Integration::STATUS_CONFIGURATION_REQUIRED,
            'is_connected' => false,
            'connected_at' => null,
            'last_tested_at' => null,
            'last_test_status' => null,
            'meta' => null,
            'created_by' => null,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn () => [
            'status' => Integration::STATUS_CONNECTED,
            'is_connected' => true,
            'connected_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'status' => Integration::STATUS_DISABLED,
            'is_connected' => false,
        ]);
    }
}
