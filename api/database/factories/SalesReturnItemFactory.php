<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesReturnItemFactory extends Factory
{
    protected $model = SalesReturnItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sales_return_id' => SalesReturn::factory(),
            'product_id' => null,
            'description' => ucfirst(fake()->words(3, true)),
            'quantity' => 1,
            'unit_price' => 50,
            'total' => 50,
        ];
    }
}
