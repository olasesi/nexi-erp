<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CrmStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmStageFactory extends Factory
{
    protected $model = CrmStage::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
