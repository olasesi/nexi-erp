<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\RentalAgreement;
use App\Models\RentalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalItemFactory extends Factory
{
    protected $model = RentalItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'rental_agreement_id' => RentalAgreement::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 5, 100),
            'billing_period' => fake()->randomElement(RentalItem::PERIODS),
            'returned_at' => null,
            'return_condition' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'returned_at' => now(),
            'return_condition' => RentalItem::CONDITION_GOOD,
        ]);
    }
}
