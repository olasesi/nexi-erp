<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->sentence(4)),
            'body' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'published_at' => now()->toDateString(),
            'expires_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDay()->toDateString()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->subDays(30)->toDateString(),
            'expires_at' => now()->subDay()->toDateString(),
        ]);
    }
}
