<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\GymMember;
use App\Models\GymPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class GymMemberFactory extends Factory
{
    protected $model = GymMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => GymPlan::factory(),
            'contact_id' => Contact::factory(),
            'member_number' => sprintf('GM-%s-%04d', now()->format('y'), fake()->unique()->numberBetween(1, 9999)),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'start_at' => now()->subMonth()->toDateString(),
            'renews_at' => now()->addMonth()->toDateString(),
            'status' => GymMember::STATUS_ACTIVE,
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['renews_at' => now()->subDays(3)->toDateString()]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => GymMember::STATUS_SUSPENDED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => GymMember::STATUS_CANCELLED]);
    }
}
