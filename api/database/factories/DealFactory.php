<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CrmStage;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'lead_id' => null,
            'contact_id' => null,
            'crm_stage_id' => CrmStage::factory(),
            'assigned_to' => null,
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'value' => fake()->randomFloat(2, 1000, 100000),
            'status' => Deal::STATUS_OPEN,
            'close_date' => fake()->dateTimeBetween('+1 week', '+4 months'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => Deal::STATUS_OPEN]);
    }

    public function won(): static
    {
        return $this->state(fn () => ['status' => Deal::STATUS_WON]);
    }

    public function lost(): static
    {
        return $this->state(fn () => ['status' => Deal::STATUS_LOST]);
    }
}
