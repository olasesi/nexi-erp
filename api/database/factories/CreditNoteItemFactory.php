<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteItem>
 */
class CreditNoteItemFactory extends Factory
{
    protected $model = CreditNoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'credit_note_id' => CreditNote::factory(),
            'description' => ucfirst(fake()->words(3, true)),
            'quantity' => 1,
            'unit_price' => 40,
            'total' => 40,
        ];
    }
}
