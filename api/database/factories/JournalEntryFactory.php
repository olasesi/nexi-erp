<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'entry_number' => 'JE-'.fake()->unique()->numerify('########'),
            'description' => fake()->sentence(),
            'entry_date' => now()->toDateString(),
            'status' => 'posted',
            'posted_at' => now(),
        ];
    }
}
