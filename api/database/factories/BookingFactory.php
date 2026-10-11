<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingResource;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(9, 0);

        return [
            'company_id' => Company::factory(),
            'resource_id' => BookingResource::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'customer_name' => fake()->name(),
            'contact_id' => Contact::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'status' => Booking::STATUS_PENDING,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_CONFIRMED]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_CHECKED_IN]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_COMPLETED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_CANCELLED]);
    }

    public function noShow(): static
    {
        return $this->state(fn () => ['status' => Booking::STATUS_NO_SHOW]);
    }
}
