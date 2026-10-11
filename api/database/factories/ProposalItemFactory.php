<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalItemFactory extends Factory
{
    protected $model = ProposalItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'proposal_id' => Proposal::factory(),
            'product_id' => null,
            'description' => ucfirst(fake()->words(3, true)),
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'total' => 100,
        ];
    }
}
