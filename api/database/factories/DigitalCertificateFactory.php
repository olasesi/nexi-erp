<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DigitalCertificate;
use App\Models\HrmEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DigitalCertificateFactory extends Factory
{
    protected $model = DigitalCertificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => HrmEmployee::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'certificate_number' => null,
            'certificate_type' => fake()->randomElement(['Training', 'Compliance', 'Professional']),
            'issued_date' => null,
            'expiry_date' => null,
            'status' => DigitalCertificate::STATUS_DRAFT,
            'serial' => null,
            'file_url' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => DigitalCertificate::STATUS_ISSUED,
            'serial' => (string) Str::uuid(),
            'certificate_number' => 'CERT-'.strtoupper(fake()->bothify('??########')),
            'issued_date' => now()->toDateString(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => DigitalCertificate::STATUS_REVOKED,
            'revoked_reason' => fake()->sentence(),
            'revoked_at' => now(),
        ]);
    }
}
