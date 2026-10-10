<?php

namespace Database\Factories;

use App\Models\AiAgentPrompt;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiAgentPromptFactory extends Factory
{
    protected $model = AiAgentPrompt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'label' => ucfirst(fake()->words(2, true)),
            'prompt' => fake()->paragraph(),
            'category' => fake()->optional()->word(),
            'is_shared' => false,
            'created_by' => User::factory(),
        ];
    }
}
