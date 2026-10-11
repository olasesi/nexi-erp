<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitNoteItemFactory extends Factory
{
    protected $model = DebitNoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'debit_note_id' => DebitNote::factory(),
            'product_id' => null,
            'description' => ucfirst(fake()->words(3, true)),
            'quantity' => 1,
            'unit_price' => 30,
            'tax_rate' => 0,
            'total' => 30,
        ];
    }
}
