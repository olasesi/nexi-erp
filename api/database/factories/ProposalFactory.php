<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contact_id' => null,
            'proposal_number' => 'PROP-'.fake()->unique()->numerify('########'),
            'title' => ucfirst(fake()->words(3, true)),
            'status' => Proposal::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'valid_until' => null,
            'currency' => 'USD',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'notes' => null,
            'terms' => null,
            'invoice_id' => null,
            'created_by' => null,
        ];
    }
}
