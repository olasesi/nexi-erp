<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Factories\Factory;

class TodoFactory extends Factory
{
    protected $model = Todo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'priority' => Todo::PRIORITY_MEDIUM,
            'status' => Todo::STATUS_PENDING,
            'due_date' => null,
            'completed_at' => null,
            'created_by' => null,
        ];
    }
}
