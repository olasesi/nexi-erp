<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(['system', 'stock', 'invoice', 'payment', 'sales', 'purchase']),
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->sentence(),
            'data' => ['source' => 'test'],
            'read_at' => null,
        ];
    }

    public function broadcast(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['company_id' => $user->company_id, 'user_id' => $user->id]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
