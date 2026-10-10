<?php

namespace Database\Factories;

use App\Models\AiAgentThread;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiAgentThreadFactory extends Factory
{
    protected $model = AiAgentThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'context' => null,
            'status' => AiAgentThread::STATUS_ACTIVE,
            'last_message_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => AiAgentThread::STATUS_ARCHIVED]);
    }
}
