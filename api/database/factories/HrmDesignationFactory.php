<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\HrmDesignation;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrmDesignationFactory extends Factory
{
    protected $model = HrmDesignation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'department_id' => null,
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'code' => strtoupper(fake()->unique()->bothify('DES-####')),
            'level' => fake()->numberBetween(1, 10),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function forDepartment(HrmDepartment $department): static
    {
        return $this->state([
            'company_id' => $department->company_id,
            'department_id' => $department->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
