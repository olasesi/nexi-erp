<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketReplyFactory extends Factory
{
    protected $model = TicketReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'contact_id' => null,
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }
}
