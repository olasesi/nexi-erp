<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => ucfirst(fake()->words(2, true)),
            'created_by' => null,
        ];
    }
}
