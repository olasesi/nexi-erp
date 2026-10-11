<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'kb_category_id' => KbCategory::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'slug' => null,
            'body' => fake()->paragraph(),
            'status' => KbArticle::STATUS_DRAFT,
            'views' => 0,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => KbArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
