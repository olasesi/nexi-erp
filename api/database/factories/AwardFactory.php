<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Company;
use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AwardFactory extends Factory
{
    protected $model = Award::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'award_type' => fake()->randomElement(['Employee of the Month', 'Spot Award', 'Long Service']),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'awarded_date' => now()->toDateString(),
            'gift' => fake()->randomElement([null, 'Gift Card', 'Trophy']),
            'created_by' => null,
        ];
    }
}
