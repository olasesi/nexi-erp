<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\WorkPermit;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkPermitFactory extends Factory
{
    protected $model = WorkPermit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'permit_number' => strtoupper(fake()->unique()->bothify('WP-#####-???')),
            'permit_type' => fake()->randomElement(['Work', 'Residence', 'Study']),
            'issuing_country' => fake()->country(),
            'issuing_authority' => fake()->company(),
            'issued_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'status' => WorkPermit::STATUS_ACTIVE,
            'document_url' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => WorkPermit::STATUS_REVOKED]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => WorkPermit::STATUS_EXPIRED,
            'expiry_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
