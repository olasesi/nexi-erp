<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\YoutubePlaylist;
use Illuminate\Database\Eloquent\Factories\Factory;

class YoutubePlaylistFactory extends Factory
{
    protected $model = YoutubePlaylist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(2, true)).' Playlist',
            'youtube_playlist_id' => 'PL'.fake()->regexify('[A-Za-z0-9]{16}'),
            'description' => fake()->sentence(),
            'visibility' => fake()->randomElement(YoutubePlaylist::VISIBILITIES),
            'videos_count' => fake()->numberBetween(0, 50),
        ];
    }
}
