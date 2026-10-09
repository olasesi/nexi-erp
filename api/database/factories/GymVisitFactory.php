<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\GymMember;
use App\Models\GymVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GymVisitFactory extends Factory
{
    protected $model = GymVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'gym_member_id' => GymMember::factory(),
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'checked_in_by' => User::factory(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'checked_in_at' => now()->subHour(),
            'checked_out_at' => now(),
        ]);
    }
}
