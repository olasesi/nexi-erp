<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\RentalAgreement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalAgreementFactory extends Factory
{
    protected $model = RentalAgreement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => Contact::factory(),
            'agreement_number' => sprintf('RN-%s-%04d', now()->format('Y'), fake()->unique()->numberBetween(1, 9999)),
            'start_at' => now()->toDateString(),
            'end_at' => now()->addWeek()->toDateString(),
            'status' => RentalAgreement::STATUS_DRAFT,
            'deposit_amount' => fake()->randomFloat(2, 0, 100),
            'late_fee_per_day' => fake()->randomFloat(2, 0, 20),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => RentalAgreement::STATUS_ACTIVE]);
    }

    public function returned(): static
    {
        return $this->state(fn () => ['status' => RentalAgreement::STATUS_RETURNED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => RentalAgreement::STATUS_CANCELLED]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => RentalAgreement::STATUS_ACTIVE,
            'start_at' => now()->subWeeks(2)->toDateString(),
            'end_at' => now()->subDays(3)->toDateString(),
        ]);
    }
}
