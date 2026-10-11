<?php

namespace Database\Factories;

use App\Models\BookingResource;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingResourceFactory extends Factory
{
    protected $model = BookingResource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => fake()->randomElement(BookingResource::TYPES),
            'capacity' => fake()->randomElement([null, 1, 2, 4, 8]),
            'is_active' => true,
            'description' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
