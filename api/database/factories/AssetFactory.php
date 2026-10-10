<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'asset_category_id' => AssetCategory::factory(),
            'asset_tag' => fake()->unique()->bothify('AST-####'),
            'name' => ucfirst(fake()->words(2, true)),
            'serial_number' => fake()->optional()->bothify('SN-########'),
            'purchase_date' => fake()->optional()->date(),
            'purchase_cost' => fake()->randomFloat(2, 100, 5000),
            'current_value' => fake()->randomFloat(2, 50, 4500),
            'condition' => Asset::CONDITION_GOOD,
            'status' => Asset::STATUS_AVAILABLE,
            'location' => fake()->optional()->city(),
            'assigned_to' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
