<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CoworkingBooking;
use App\Models\CoworkingUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoworkingBookingFactory extends Factory
{
    protected $model = CoworkingBooking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(9, 0);

        return [
            'company_id' => Company::factory(),
            'unit_id' => CoworkingUnit::factory(),
            'contact_id' => Contact::factory(),
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(3),
            'seats' => 1,
            'status' => CoworkingBooking::STATUS_REQUESTED,
            'price' => 0,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => CoworkingBooking::STATUS_CONFIRMED]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => ['status' => CoworkingBooking::STATUS_CHECKED_IN]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['status' => CoworkingBooking::STATUS_CHECKED_OUT]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => CoworkingBooking::STATUS_CANCELLED]);
    }
}
