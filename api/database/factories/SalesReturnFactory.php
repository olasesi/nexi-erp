<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesReturnFactory extends Factory
{
    protected $model = SalesReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => null,
            'invoice_id' => null,
            'return_number' => 'SRT-'.fake()->unique()->numerify('########'),
            'return_date' => now()->toDateString(),
            'status' => SalesReturn::STATUS_DRAFT,
            'reason' => fake()->sentence(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'notes' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
