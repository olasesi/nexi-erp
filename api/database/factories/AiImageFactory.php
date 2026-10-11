<?php

namespace Database\Factories;

use App\Models\AiImage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiImageFactory extends Factory
{
    protected $model = AiImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(),
            'negative_prompt' => null,
            'style' => null,
            'size' => '512x512',
            'provider' => null,
            'model' => null,
            'url' => null,
            'status' => AiImage::STATUS_PENDING,
            'generated_at' => null,
            'meta' => null,
        ];
    }

    public function generated(): static
    {
        return $this->state(fn () => [
            'url' => 'data:image/svg+xml;base64,c3R1Yg==',
            'provider' => 'stub',
            'model' => 'stub-image-1',
            'status' => AiImage::STATUS_GENERATED,
            'generated_at' => now(),
        ]);
    }
}
