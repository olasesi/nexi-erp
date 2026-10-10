<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'form_id' => Form::factory(),
            'submitted_by' => User::factory(),
            'status' => FormSubmission::STATUS_NEW,
            'payload' => ['name' => fake()->name(), 'email' => fake()->safeEmail()],
        ];
    }
}
