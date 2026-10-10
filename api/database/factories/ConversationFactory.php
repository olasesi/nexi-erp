<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject' => ucfirst(fake()->words(3, true)),
            'channel' => fake()->randomElement(Conversation::CHANNELS),
            'contact_id' => null,
            'created_by' => User::factory(),
            'assigned_to' => null,
            'status' => Conversation::STATUS_OPEN,
            'is_group' => false,
            'last_message_at' => null,
        ];
    }
}
