<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'form_id' => Form::factory(),
            'label' => ucfirst(fake()->words(2, true)),
            'name' => fake()->slug(2),
            'type' => 'text',
            'placeholder' => null,
            'options' => null,
            'is_required' => false,
            'position' => 0,
        ];
    }
}
