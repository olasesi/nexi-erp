<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'warehouse_id' => Warehouse::factory(),
            'reference_no' => 'ADJ-'.fake()->unique()->numerify('######'),
            'reason' => fake()->sentence(),
            'adjusted_by' => null,
        ];
    }
}
