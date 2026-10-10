<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Rota;
use Illuminate\Database\Eloquent\Factories\Factory;

class RotaFactory extends Factory
{
    protected $model = Rota::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'status' => Rota::STATUS_DRAFT,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Rota::STATUS_PUBLISHED]);
    }
}
