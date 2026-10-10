<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'ticket_number' => sprintf('TKT-%s-%04d', now()->format('y'), fake()->unique()->numberBetween(1, 99999)),
            'subject' => ucfirst(fake()->sentence(4)),
            'description' => fake()->paragraph(),
            'status' => Ticket::STATUS_OPEN,
            'priority' => fake()->randomElement(Ticket::PRIORITIES),
            'category' => fake()->word(),
            'contact_id' => Contact::factory(),
            'assigned_to' => null,
            'created_by' => User::factory(),
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => Ticket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
