<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmDepartment;
use App\Models\HrmDesignation;
use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrmEmployeeFactory extends Factory
{
    protected $model = HrmEmployee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-#####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'user_id' => null,
            'department_id' => HrmDepartment::factory(),
            'designation_id' => HrmDesignation::factory(),
            'manager_id' => null,
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'date_of_joining' => fake()->dateTimeBetween('-8 years', 'now')->format('Y-m-d'),
            'employment_status' => HrmEmployee::STATUS_ACTIVE,
            'salary' => fake()->randomFloat(2, 2000, 12000),
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function resigned(): static
    {
        return $this->state([
            'employment_status' => HrmEmployee::STATUS_RESIGNED,
            'is_active' => false,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(['employment_status' => HrmEmployee::STATUS_ON_LEAVE]);
    }

    public function probation(): static
    {
        return $this->state(['employment_status' => HrmEmployee::STATUS_PROBATION]);
    }
}
