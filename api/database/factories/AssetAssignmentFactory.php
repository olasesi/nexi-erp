<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetAssignmentFactory extends Factory
{
    protected $model = AssetAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'asset_id' => Asset::factory(),
            'user_id' => User::factory(),
            'contact_id' => null,
            'assigned_at' => now(),
            'returned_at' => null,
            'condition_on_return' => null,
            'notes' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'returned_at' => now()->addDays(3),
            'condition_on_return' => Asset::CONDITION_GOOD,
        ]);
    }
}
