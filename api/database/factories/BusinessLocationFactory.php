<?php

namespace Database\Factories;

use App\Models\BusinessLocation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessLocation>
 */
class BusinessLocationFactory extends Factory
{
    protected $model = BusinessLocation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->company(),
            'location_id' => 'BL-'.fake()->unique()->numerify('####'),
            'landmark' => fake()->streetName(),
            'city' => fake()->city(),
            'zip_code' => fake()->postcode(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'price_group' => fake()->optional()->randomElement(['p1', 'p2', 'p3']),
            'invoice_scheme' => 'default',
            'invoice_layout_for_pos' => 'default',
            'invoice_layout_for_sale' => 'default',
        ];
    }
}
