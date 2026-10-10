<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventAttendeeFactory extends Factory
{
    protected $model = EventAttendee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'contact_id' => null,
            'email' => null,
            'status' => EventAttendee::STATUS_INVITED,
        ];
    }
}
