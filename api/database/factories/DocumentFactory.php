<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'folder_id' => null,
            'name' => ucfirst(fake()->words(2, true)),
            'type' => Document::TYPE_FILE,
            'mime_type' => null,
            'size' => fake()->numberBetween(1, 100000),
            'url' => null,
            'content' => null,
            'version' => 1,
            'is_public' => false,
            'shared_with' => null,
            'created_by' => null,
        ];
    }
}
