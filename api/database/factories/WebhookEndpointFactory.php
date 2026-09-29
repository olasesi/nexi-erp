<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Endpoint '.fake()->unique()->numerify('####'),
            'url' => 'https://example.com/hooks/'.fake()->unique()->numerify('########'),
            'secret' => Str::random(48),
            'events' => ['invoice.created'],
            'is_active' => true,
            'description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
