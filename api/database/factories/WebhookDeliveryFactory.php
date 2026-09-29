<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
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
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => 'invoice.created',
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'payload' => [
                'event' => 'invoice.created',
                'sent_at' => now()->toISOString(),
                'data' => ['id' => 1, 'type' => 'App\\Models\\Invoice'],
            ],
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookDelivery::STATUS_SUCCEEDED,
            'attempts' => 1,
            'response_status' => 200,
            'delivered_at' => now(),
        ]);
    }
}
