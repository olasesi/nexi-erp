<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'credit_note_number' => 'CN-'.fake()->unique()->numerify('########'),
            'status' => CreditNote::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
        ];
    }
}
