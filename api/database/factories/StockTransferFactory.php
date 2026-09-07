<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'reference_no' => 'TRF-'.fake()->unique()->numerify('######'),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
