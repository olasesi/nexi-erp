<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\YoutubeChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class YoutubeChannelFactory extends Factory
{
    protected $model = YoutubeChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'channel_id' => 'UC'.fake()->regexify('[A-Za-z0-9]{20}'),
            'title' => ucfirst(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'subscriber_count' => fake()->numberBetween(0, 100000),
            'video_count' => fake()->numberBetween(0, 500),
            'is_connected' => true,
            'connected_at' => now(),
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => [
            'is_connected' => false,
            'connected_at' => null,
        ]);
    }
}
