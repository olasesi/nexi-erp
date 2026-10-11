<?php

namespace Database\Factories;

use App\Models\AiDocument;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiDocumentFactory extends Factory
{
    protected $model = AiDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'prompt' => fake()->paragraph(),
            'content' => null,
            'template' => null,
            'language' => 'en',
            'provider' => null,
            'model' => null,
            'tokens' => null,
            'status' => AiDocument::STATUS_PENDING,
            'generated_at' => null,
            'meta' => null,
        ];
    }

    public function generated(): static
    {
        return $this->state(fn () => [
            'content' => fake()->paragraph(),
            'provider' => 'stub',
            'model' => 'stub-document-1',
            'tokens' => 42,
            'status' => AiDocument::STATUS_GENERATED,
            'generated_at' => now(),
        ]);
    }
}
