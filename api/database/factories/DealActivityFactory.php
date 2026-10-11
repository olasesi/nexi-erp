<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Deal;
use App\Models\DealActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealActivityFactory extends Factory
{
    protected $model = DealActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'deal_id' => Deal::factory(),
            'user_id' => null,
            'type' => DealActivity::TYPE_NOTE,
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'scheduled_at' => null,
            'completed_at' => null,
            'status' => DealActivity::STATUS_PENDING,
            'created_by' => null,
        ];
    }
}
