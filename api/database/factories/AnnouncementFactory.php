<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->sentence(3)),
            'body' => fake()->paragraph(),
            'audience' => Announcement::AUDIENCE_ALL,
            'audience_value' => null,
            'priority' => Announcement::PRIORITY_NORMAL,
            'status' => Announcement::STATUS_DRAFT,
            'publish_at' => null,
            'expires_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Announcement::STATUS_PUBLISHED,
            'publish_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => Announcement::STATUS_ARCHIVED]);
    }
}
