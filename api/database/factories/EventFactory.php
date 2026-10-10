<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(Event::TYPES),
            'location' => fake()->city(),
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->addHour()->startOfHour(),
            'all_day' => false,
            'status' => Event::STATUS_SCHEDULED,
            'reminder_minutes' => null,
            'created_by' => User::factory(),
        ];
    }
}
