<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'contact_id' => null,
            'direction' => fake()->randomElement(Message::DIRECTIONS),
            'body' => fake()->paragraph(),
            'attachments' => null,
            'read_at' => null,
        ];
    }
}
