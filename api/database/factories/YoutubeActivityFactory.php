<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\YoutubeActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class YoutubeActivityFactory extends Factory
{
    protected $model = YoutubeActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => fake()->randomElement(YoutubeActivity::TYPES),
            'youtube_video_id' => null,
            'description' => fake()->sentence(),
            'occurred_at' => now(),
            'meta' => [],
        ];
    }
}
