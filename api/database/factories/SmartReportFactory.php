<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SmartReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmartReportFactory extends Factory
{
    protected $model = SmartReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional()->sentence(),
            'source' => fake()->randomElement(SmartReport::SOURCES),
            'columns' => null,
            'filters' => null,
            'group_by' => null,
            'aggregate' => null,
            'aggregate_field' => null,
            'chart_type' => 'table',
            'is_shared' => false,
            'created_by' => User::factory(),
        ];
    }
}
