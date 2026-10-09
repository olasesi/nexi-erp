<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrmDepartmentFactory extends Factory
{
    protected $model = HrmDepartment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'code' => strtoupper(fake()->unique()->bothify('DEP-####')),
            'description' => fake()->optional()->sentence(),
            'manager_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function managed(): static
    {
        return $this->state(['manager_id' => User::factory()]);
    }
}
