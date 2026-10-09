<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class LaundryItemFactory extends Factory
{
    protected $model = LaundryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 2, 20);

        return [
            'company_id' => Company::factory(),
            'laundry_order_id' => LaundryOrder::factory(),
            'product_id' => null,
            'description' => ucfirst(fake()->word()).' garment',
            'service' => fake()->randomElement(LaundryItem::SERVICES),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'weight_kg' => null,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
