<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\YoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

class YoutubeVideoFactory extends Factory
{
    protected $model = YoutubeVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'youtube_video_id' => fake()->regexify('[A-Za-z0-9_-]{11}'),
            'description' => fake()->sentence(),
            'url' => fake()->url(),
            'youtube_playlist_id' => null,
            'duration_seconds' => fake()->numberBetween(15, 3600),
            'views' => fake()->numberBetween(0, 100000),
            'status' => YoutubeVideo::STATUS_DRAFT,
            'published_at' => null,
        ];
    }
}
