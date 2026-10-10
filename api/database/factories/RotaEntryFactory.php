<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\Rota;
use App\Models\RotaEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class RotaEntryFactory extends Factory
{
    protected $model = RotaEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'rota_id' => Rota::factory(),
            'employee_id' => HrmEmployee::factory(),
            'shift_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'shift_type' => fake()->randomElement(['morning', 'evening', 'night']),
            'notes' => null,
        ];
    }
}
