<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PosRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosRegisterFactory extends Factory
{
    protected $model = PosRegister::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'warehouse_id' => null,
            'name' => fake()->company().' Register',
            'code' => strtoupper(fake()->unique()->bothify('REG-###')),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
