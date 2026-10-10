<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormFactory extends Factory
{
    protected $model = Form::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'status' => Form::STATUS_DRAFT,
            'is_template' => false,
            'settings' => null,
            'submissions_count' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Form::STATUS_ACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => Form::STATUS_ARCHIVED,
        ]);
    }
}
