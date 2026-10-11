<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'slug' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
