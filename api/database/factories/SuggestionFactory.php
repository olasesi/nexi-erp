<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SuggestionFactory extends Factory
{
    protected $model = Suggestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->sentence(3)),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(Suggestion::CATEGORIES),
            'status' => Suggestion::OPEN,
            'submitted_by' => User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Suggestion::ACCEPTED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => Suggestion::IN_REVIEW]);
    }
}
