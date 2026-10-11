<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DebitNote;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitNoteFactory extends Factory
{
    protected $model = DebitNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'debit_note_number' => 'DN-'.fake()->unique()->numerify('########'),
            'issue_date' => now(),
            'status' => DebitNote::STATUS_DRAFT,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'applied_amount' => 0,
            'balance' => 0,
            'reason' => fake()->sentence(),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
