<?php

namespace Database\Factories;

use App\Models\AiAgentMessage;
use App\Models\AiAgentThread;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiAgentMessageFactory extends Factory
{
    protected $model = AiAgentMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'thread_id' => AiAgentThread::factory(),
            'role' => AiAgentMessage::ROLE_USER,
            'body' => fake()->sentence(),
            'meta' => null,
        ];
    }
}
